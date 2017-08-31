<?php namespace App\Http\Controllers;

use App\Form;
use App\Field;
use App\Record;
use App\Revision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RevisionController extends Controller {

    /*
    |--------------------------------------------------------------------------
    | Revision Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles record revisions to preserve history of a record
    |
    */

    /**
     * Constructs controller and makes sure user is authenticated.
     */
    public function __construct() {
        $this->middleware('auth');
        $this->middleware('active');
    }

    /**
     * Gets the main record revision view.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @return View
     */
    public function index($pid, $fid) {
        if(!FormController::validProjForm($pid, $fid))
            return redirect('projects/'.$pid)->with('k3_global_error', 'form_invalid');

        $form = FormController::getForm($fid);

        if(!(\Auth::user()->isFormAdmin($form)))
            return redirect('projects/'.$pid)->with('k3_global_error', 'not_form_admin');

        $revisions = DB::table('revisions')->where('fid', '=', $fid)->orderBy('created_at', 'desc')->take(50)->get();

        $rid_array = array();
        foreach($revisions as $revision) {
            $rid_array[] = $revision->rid;
        }
        $rid_array = array_values(array_unique($rid_array));

        $form = FormController::getForm($fid);
        $pid = $form->pid;
        $records = array();

        $temp = array_values(array_unique(Revision::lists('rid')->all()));

        for($i=0; $i < count($temp); $i++) {
            if(in_array($temp[$i], $rid_array))
                $records[$temp[$i]] = $pid . '-' . $form->fid . '-' . $temp[$i];
        }

        return view('revisions.index', compact('revisions', 'records', 'form'));
    }

    /**
     * Gets view for an individual records revision history.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $rid - Record ID
     * @return View
     */
    public function show($pid, $fid, $rid) {
        if(!RecordController::validProjFormRecord($pid, $fid, $rid))
            return redirect('projects')->with('k3_global_error', 'record_invalid');

        $firstRevision = DB::table('revisions')->where('rid', '=', $rid)->orderBy('created_at','desc')->first();
        if(is_null($firstRevision))
            return redirect()->action('RevisionController@index', ['pid' => $pid,'fid' => $fid])->with('k3_global_error', 'no_revision_history');

        $owner = DB::table('revisions')->where('rid', '=', $rid)->orderBy('created_at','desc')->first()->owner;

        $form = FormController::getForm($fid);

        if(!(\Auth::user()->isFormAdmin($form)) && \Auth::user()->id != $owner)
            return redirect('projects/'.$pid)->with('k3_global_error', 'revision_permission_issue');

        $revisions = DB::table('revisions')->where('rid', '=', $rid)->orderBy('created_at','desc')->take(50)->get();

        $records = array();

        $temp = array_values(array_unique(Revision::lists('rid')->all()));

        for($i=0; $i < count($temp); $i++) {
            $records[$temp[$i]] = $pid.'-'.$form->fid.'-'.$temp[$i];
        }
        $record = RecordController::getRecord($rid);

        return view('revisions.index', compact('revisions', 'records', 'form', 'message', 'record'))->render();
    }

    /**
     * Execute a rollback to restore a record to a previous revision.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function rollback(Request $request) {
        $revision = Revision::where('id', '=', $request->revision)->first();
        $form = FormController::getForm($revision->fid);

        //Keep in mind that the rollback is the reverse of the revision type (i.e. executing a rollback on revision of
        // type CREATE, will delete the created record).
        if($revision->type == Revision::CREATE) {
            $record = Record::where('rid', '=', $revision->rid)->first();
            self::storeRevision($record->rid, Revision::DELETE);
            $record->delete();

            return response()->json(["status"=>true,"message"=>"record_deleted","deleted_kid"=>$record->kid],200);
        } else if($revision->type == Revision::DELETE) {
            if(RecordController::exists($revision->rid)) {
                return response()->json(["status"=>false,"message"=>"record_already_exists"],500);
            } else {
                // We must create a new record
                $record = new Record();
                $record->rid = $revision->rid;
                $record->fid = $revision->fid;
                $record->pid = $form->pid;
                $record->owner = $revision->owner;
                $record->save();
                $record->kid = $record->pid . '-' . $record->fid . '-' . $record->rid;
                $record->save();

                self::rollback_routine($record, $form, $revision, false);
                self::storeRevision($record->rid, Revision::CREATE);

                return response()->json(["status"=>true,"message"=>"record_created","created_kid"=>$record->kid],200);
            }
        } else {
            $record = RecordController::getRecord($revision->rid);
            self::rollback_routine($record, $form, $revision, true);

            return response()->json(["status"=>true,"message"=>"record_modified","modified_kid"=>$record->kid],200);
        }
    }

    /**
     * Performs the actual rollback.
     *
     * @param  Record $record - Record to rollback
     * @param  Form $form - Form that owns record
     * @param  Revision $revision - Revision to pull data from
     * @param  bool $is_rollback - Will new revision allow for rollback
     */
    public static function rollback_routine(Record $record, Form $form, Revision $revision, $is_rollback) {
        if($is_rollback) {
            $new_revision = self::storeRevision($record->rid, Revision::ROLLBACK);
            $new_revision->oldData = $revision->data;
            $new_revision->save();
        }

        // Since we'll be passing around the revision object, we decode its data now.
        // This won't be saved and is done for efficiency.
        $revision->data = json_decode($revision->data, true);

        foreach($form->fields()->get() as $field) {
            $typedField = $field->getTypedFieldFromRID($record->rid);
            if(!is_null($typedField)){
                //Field exists in record already
                $typedField->rollbackField($field, $revision, true);
            } else {
                //Most likely restoring from a deleted field
                $field->getTypedField()->rollbackField($field, $revision, false);
            }
        }
    }

    /**
     * Stores a record revision.
     *
     * @param  int $rid - Record ID
     * @param  string $type - Revision type
     * @return Revision - The new revision model
     */
    public static function storeRevision($rid, $type) {
        $revision = new Revision();
        $record = RecordController::getRecord($rid);

        $fid = $record->form()->first()->fid;
        $revision->fid = $fid;
        $revision->rid = $record->rid;
        $revision->owner = $record->owner;

        if(\Auth::guest())
            $revision->username = 'admin';
        else
            $revision->username = \Auth::user()->username;
        $revision->type = $type;

        $revision->data = self::buildDataArray($record);

        $revision->rollback = 1;
        $revision->save();

        return $revision;
    }

    /**
     * Builds the data array for the revision.
     *
     * @param  Record $record - Record to pull data from
     * @return string - Json string of the data for DB storage
     */
    public static function buildDataArray(Record $record) {
        $data = array();
        $fields = Field::where("fid", "=", $record->fid)->get();

        foreach($fields as $field) {
            $typed_field = $field->getTypedFieldFromRID($record->rid);

            $data[$field->type][$field->flid]['name'] = $field->name;
            if(is_null($typed_field))
                $data[$field->type][$field->flid]['data'] = null;
            else
                $data[$field->type][$field->flid]['data'] = $typed_field->getRevisionData($field);
        }

        return json_encode($data);
    }

    /**
     * Turns off rollback for all revisions in a form.
     *
     * @param  int $fid - Form ID
     */
    public static function wipeRollbacks($fid) {
        Revision::where('fid','=',$fid)->update(["rollback" => 0]);
    }
}
