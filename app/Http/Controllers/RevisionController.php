<?php namespace App\Http\Controllers;

use App\Form;
use App\Record;
use App\Revision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
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

        $pagination = app('request')->input('page-count') === null ? 10 : app('request')->input('page-count');
        $order = app('request')->input('order') === null ? 'lmd' : app('request')->input('order');
        $order_type = substr($order, 0, 2) === "lm" ? "created_at" : "id";
        $order_direction = substr($order, 2, 3) === "a" ? "asc" : "desc";
        $revisions = Revision::where('form_id', '=', $fid)->orderBy($order_type, $order_direction)->paginate($pagination);

        $all_form_revisions = Revision::where('form_id', '=', $fid)->get();
        $records = array();
        foreach($all_form_revisions as $revision) {
            $records[$revision->record_kid] = $revision->record_kid;
        }

        $notification = array(
          'message' => '',
          'description' => '',
          'warning' => false,
          'static' => false
        );

        return view('revisions.index', compact('revisions', 'records', 'form', 'notification', [
            'revisions' => $revisions->appends(Input::except('page'))
        ]));
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
        if(!FormController::validProjForm($pid, $fid))
            return redirect('projects/'.$pid)->with('k3_global_error', 'form_invalid');

        $kid = $pid.'-'.$fid.'-'.$rid;
        if(Revision::where('record_kid','=',$kid)->count() == 0)
            return redirect()->action('RevisionController@index', ['pid' => $pid,'fid' => $fid])->with('k3_global_error', 'no_revision_history');

        //Owner of record should be able to access to
        $owner = Revision::where('record_kid','=',$kid)->orderBy('created_at','desc')->first()->owner;

        $form = FormController::getForm($fid);

        if(!(\Auth::user()->isFormAdmin($form)) && \Auth::user()->id != $owner)
            return redirect('projects/'.$pid)->with('k3_global_error', 'revision_permission_issue');

        $pagination = app('request')->input('page-count') === null ? 10 : app('request')->input('page-count');
        $order = app('request')->input('order') === null ? 'lmd' : app('request')->input('order');
        $order_type = substr($order, 0, 2) === "lm" ? "created_at" : "id";
        $order_direction = substr($order, 2, 3) === "a" ? "asc" : "desc";
        $revisions = Revision::where('record_kid', '=', $kid)->orderBy($order_type, $order_direction)->paginate($pagination);

        $all_form_revisions = Revision::where('form_id', '=', $fid)->get();
        $records = array();
        foreach($all_form_revisions as $revision) {
            $records[$revision->record_kid] = $revision->record_kid;
        }

        $record = RecordController::getRecord($kid);

        $notification = array(
          'message' => '',
          'description' => '',
          'warning' => false,
          'static' => false
        );

        return view('revisions.index', compact('revisions', 'records', 'form', 'message', 'record', 'rid', 'notification'));
    }

    /**
     * Stores a record revision.
     *
     * @param  Record $record - Record model
     * @param  string $type - Revision type
     * @param  Record $oldRecord - Old data to save for edits
     * @return Revision - The new revision model
     */
    public static function storeRevision($record, $type, $oldRecord = null) {
        $revision = new Revision();

        $fid = $record->form_id;
        $revision->form_id = $fid;
        $revision->record_kid = $record->kid;
        if(\Auth::guest())
            $revision->owner = 'admin';
        else
            $revision->owner = \Auth::user()->username;

        $revArray = [];
        $revArray['type'] = $type;

        switch($type) {
            case Revision::CREATE:
                $revArray['data'] = self::buildDataArray($record);
                $revArray['oldData'] = null;
                break;
            case Revision::EDIT:
            case Revision::ROLLBACK:
                $revArray['data'] = self::buildDataArray($record);
                $revArray['oldData'] = self::buildDataArray($oldRecord);
                break;
            case Revision::DELETE:
                $revArray['data'] = null;
                $revArray['oldData'] = self::buildDataArray($record);;
                break;
        }

        $revision->revision = $revArray;
        $revision->rollback = 1;
        $revision->save();

        return $revision;
    }

    /**
     * Builds the data array for the revision.
     *
     * @param  Record $record - Record to pull data from
     * @return array - The data for DB storage
     */
    public static function buildDataArray(Record $record) {
        $data = [];
        $form = FormController::getForm($record->form_id);

        foreach(array_keys($form->layout['fields']) as $flid) {
            $data[$flid] = $record->{$flid};
        }

        return $data;
    }

    /**
     * Formats a revision for display
     *
     * @param int $id - The ID of the revision
     * @return array - The formatted data in an array
     */
    public static function formatRevision($id) {
        $revision = Revision::where('id',$id)->get()->first();
        $form = FormController::getForm($revision->form_id);
        $revData = $revision->revision;

        $formatted = array();
        switch($revData['type']) {
            case Revision::CREATE:
                foreach($form->layout['fields'] as $flid => $field) {
                    $fieldMod = $form->getFieldModel($field['type']);
                    if(is_null($revData['data'][$flid]))
                        $formatted[$flid] = 'No Field Data';
                    else
                        $formatted[$flid] = $fieldMod->processRevisionData($revData['data'][$flid]);
                }
                break;
            case Revision::EDIT:
            case Revision::ROLLBACK:
                foreach($form->layout['fields'] as $flid => $field) {
                    $fieldMod = $form->getFieldModel($field['type']);
                    if(is_null($revData['data'][$flid]))
                        $formatted['current'][$flid] = 'No Field Data';
                    else
                        $formatted['current'][$flid] = $fieldMod->processRevisionData($revData['data'][$flid]);
                    if(is_null($revData['oldData'][$flid]))
                        $formatted['old'][$flid] = 'No Field Data';
                    else
                        $formatted['old'][$flid] = $fieldMod->processRevisionData($revData['oldData'][$flid]);
                }
                break;
            case Revision::DELETE:
                foreach($form->layout['fields'] as $flid => $field) {
                    $fieldMod = $form->getFieldModel($field['type']);
                    if(is_null($revData['oldData'][$flid]))
                        $formatted[$flid] = 'No Field Data';
                    else
                        $formatted[$flid] = $fieldMod->processRevisionData($revData['oldData'][$flid]);
                }
                break;
        }

        return $formatted;
    }

    /**
     * Execute a rollback to restore a record to a previous revision.
     *
     * @param  Request $request [revision]
     * @return JsonResponse
     */
    public function rollback(Request $request) {
        $revision = Revision::where('id', '=', $request->revision)->first();
        $form = FormController::getForm($revision->form_id);

        if(is_null($revision))
            return response()->json(["status"=>false,"message"=>"revision_doesnt_exist"],500);

        //Keep in mind that the rollback is the reverse of the revision type (i.e. executing a rollback on revision of
        // type CREATE, will delete the created record).
        switch($revision->revision['type']) {
            case Revision::CREATE:
                $record = RecordController::getRecord($revision->record_kid);

                if(is_null($record))
                    return response()->json(["status"=>false,"message"=>"record_doesnt_exist"],500);

                self::storeRevision($record, Revision::DELETE);

                $record->delete();

                return response()->json(["status"=>true,"message"=>"record_deleted","deleted_kid"=>$record->kid],200);
                break;
            case Revision::DELETE:
                if(RecordController::exists($revision->record_kid)) {
                    return response()->json(["status"=>false,"message"=>"record_already_exists"],500);
                } else {
                    // We must create a new record
                    $record = new Record(array(),$form->id);
                    $record->id = explode('-',$revision->record_kid)[2];
                    $record->project_id = $form->project_id;
                    $record->form_id = $form->id;
                    $record->owner = \Auth::user()->id;
                    $record->kid = $revision->record_kid;
                    $record->save();

                    self::rollback_routine($record, $revision, false);
                    self::storeRevision($record, Revision::CREATE);

                    return response()->json(["status"=>true,"message"=>"record_created","created_kid"=>$record->kid],200);
                }
                break;
            case Revision::EDIT:
            case Revision::ROLLBACK:
                $record = RecordController::getRecord($revision->record_kid);
                self::rollback_routine($record, $revision);

                return response()->json(["status"=>true,"message"=>"record_modified","modified_kid"=>$record->kid],200);
                break;
        }
    }

    /**
     * Performs the actual rollback.
     *
     * @param  Record $record - Record to rollback
     * @param  Revision $revision - Revision to pull data from
     * @param  bool $is_rollback - Basically is this revision type Edit or Rollback, or are we reversing a Delete revision
     */
    public static function rollback_routine(Record $record, Revision $revision, $is_rollback = true) {
        if($is_rollback)
            $oldRecordCopy = $record->replicate();

        foreach($revision->revision['oldData'] as $flid => $data) {
            $record->{$flid} = $data;
        }

        $record->save();

        if($is_rollback)
            self::storeRevision($record, Revision::ROLLBACK, $oldRecordCopy);
    }

    /**
     * Turns off rollback for all revisions in a form.
     *
     * @param  int $fid - Form ID
     */
    public static function wipeRollbacks($fid) {
        Revision::where('form_id','=',$fid)->update(["rollback" => 0]);
    }

    /**
     * Gets the number of revisions for a specific record
     *
     * @param  string $kid - The KID of the record
     * @return int - The number of revisions for the specified record
     */
    public static function getRevisionCount($kid) {
       return Revision::where('record_kid', $kid)->count();
    }
}
