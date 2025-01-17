<?php namespace App\Http\Controllers;

use App\Form;
use App\Http\Requests\FieldRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class FieldController extends Controller {

    /*
    |--------------------------------------------------------------------------
    | Field Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the creation and management of fields in kora
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
     * Gets the field creation view.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $pageIndex - Page that will own this field
     * @return View
     */
	public function create($pid, $fid, $pageIndex) {
        if(!FormController::validProjForm($pid, $fid))
            return redirect('projects/'.$pid)->with('k3_global_error', 'form_invalid');

        if(!self::checkPermissions($fid, 'create'))
            return redirect('projects/'.$pid.'/forms/'.$fid.'/fields')->with('k3_global_error', 'cant_create_field');

        $form = FormController::getForm($fid);
        $validFieldTypes = Form::$validFieldTypes;
        $validComboListFieldTypes = Form::$validComboListFieldTypes;

        return view('fields.create', compact('form','pageIndex', 'validFieldTypes', 'validComboListFieldTypes'));
	}

    /**
     * Saves a new field model and redirects to form page.
     *
     * @param  FieldRequest $request
     * @return Redirect
     */
	public function store(FieldRequest $request) {
	    if(!FormController::validProjForm($request->pid, $request->fid))
            return redirect('projects/'.$request->pid)->with('k3_global_error', 'form_invalid');

	    $field = [];
        $form = FormController::getForm($request->fid);
        $flid = slugFormat($request->name, $form->project_id, $form->id);
        $layout = $form->layout;

        //Make sure slug doesn't already exist
        if(array_key_exists($flid,$layout["fields"]))
            return redirect('projects/'.$request->pid.'/forms/'.$request->fid)->with('k3_global_error', 'field_name_error');

        //Fill out its data
        $field['type'] = $request->type;
        $field['name'] = $request->name;
        $field['alt_name'] = $request->altName;
        $field['description'] = $request->desc;
        $field['default'] = null;
        $field['required'] = isset($request->required) && $request->required ? 1 : 0;
        $field['searchable'] = isset($request->searchable) && $request->searchable ? 1 : 0;
        $field['advanced_search'] = isset($request->advsearch) && $request->advsearch ? 1 : 0;
        $field['external_search'] = isset($request->extsearch) && $request->extsearch ? 1 : 0;
        $field['viewable'] = isset($request->viewable) && $request->viewable ? 1 : 0;
        $field['viewable_in_results'] = isset($request->viewresults) && $request->viewresults ? 1 : 0;
        $field['external_view'] = isset($request->extview) && $request->extview ? 1 : 0;

        // Combo List Specific
        $options = array();
        if($request->type == Form::_COMBO_LIST) {
            foreach(['one' => 1, 'two' => 2] as $seq => $num) {
                $slug = slugFormat(
                    trim($request->{'cfname' . $num}),
                    $form->project_id,
                    $form->id
                );
                $options[$seq] = [
                    'type' => $request->{'type' . $seq},
                    'name' => $slug
                ];
                $field[$seq] = [
                    'type' => $request->{'type' . $seq},
                    'name' => trim($request->{'cfname' . $num}),
                    'flid' => $slug,
                    'default' => null
                ];
            }
        }

        //Field Specific Stuff
        $fieldMod = $form->getFieldModel($request->type);
        $fieldMod->addDatabaseColumn($form->id, $flid, $fieldMod::FIELD_DATABASE_METHOD, $options);
        if(!$request->advanced) {
            if($request->type == Form::_COMBO_LIST) {
                foreach(['one', 'two'] as $seq) {
                    $field[$seq]['options'] = $fieldMod->getDefaultOptions($options[$seq]['type']);
                }
            } else {
                $field['options'] = $fieldMod->getDefaultOptions($options);
            }
        }

        if($request->advanced || in_array($request->type,Form::$enumFields)) {
            $field = $form->getFieldModel($request->type)->updateOptions($field, $request);
        }

        //Add to form
        $layout['fields'][$flid] = $field;
        $layout['pages'][$request->page_id]["flids"][] = $flid;
        $form->layout = $layout;
        $form->save();

        //A field has been changed, so current record rollbacks become invalid.
        RevisionController::wipeRollbacks($form->id);

        return redirect('projects/'.$request->pid.'/forms/'.$request->fid)->with('k3_global_error', 'field_advanced_error');
	}

    /**
     * Gets and displays the field options page for a particular field.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $flid - Field ID
     * @return View
     */
	public function show($pid, $fid, $flid) {
        if(!self::validProjFormField($pid, $fid, $flid))
            return redirect('projects/'.$pid.'/forms/'.$fid)->with('k3_global_error', 'field_invalid');

        if(!self::checkPermissions($fid, 'edit'))
            return redirect('projects/'.$pid.'/forms/'.$fid.'/fields')->with('k3_global_error', 'cant_edit_field');

        $field = self::getField($flid,$fid);
        $form = FormController::getForm($fid);
        $proj = ProjectController::getProject($pid);

        $presets = FieldValuePresetController::getPresetsSupported($pid,$field);

        return view($form->getFieldModel($field['type'])->getFieldOptionsView(), compact('flid', 'field', 'form', 'proj', 'presets'));
	}

    /**
     * DEPRECATED - We are no longer editing the field separate from it's options. Therefore the options page above
     *               will be the main edit view. This view will simply bounce to the options page.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $flid - Field ID
     * @return Redirect
     */
	public function edit($pid, $fid, $flid) {
        return redirect('projects/'.$pid.'/forms/'.$fid.'/fields/'.$flid.'/options');
	}

    /**
     * Update the options for a particular field.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $flid - Field ID
     * @param  FieldRequest $request
     * @return Redirect
     */
    public function update($pid, $fid, $flid, FieldRequest $request) {
        if(!self::validProjFormField($pid, $fid, $flid))
            return redirect('projects/'.$pid.'/forms/'.$fid)->with('k3_global_error', 'field_invalid');

        $field = self::getField($flid,$fid);
        $form = FormController::getForm($fid);

        $field['name'] = $request->name;
        $field['alt_name'] = $request->altName;
        $newFlid = str_replace(" ","_", $request->name).'_'.$form->project_id.'_'.$form->id.'_';

        $field['description'] = $request->desc;
        $field['default'] = null;
        $field['required'] = isset($request->required) && $request->required ? 1 : 0;
        $field['searchable'] = isset($request->searchable) && $request->searchable ? 1 : 0;
        $field['advanced_search'] = isset($request->advsearch) && $request->advsearch ? 1 : 0;
        $field['external_search'] = isset($request->extsearch) && $request->extsearch ? 1 : 0;
        $field['viewable'] = isset($request->viewable) && $request->viewable ? 1 : 0;
        $field['viewable_in_results'] = isset($request->viewresults) && $request->viewresults ? 1 : 0;
        $field['external_view'] = isset($request->extview) && $request->extview ? 1 : 0;
        $field = $form->getFieldModel($field['type'])->updateOptions($field, $request, $flid);

        // Combo List Specific
        $comboPrefix = array();
        if($request->type == Form::_COMBO_LIST) {
            $comboPrefix['tablePrefix'] = $flid;

            foreach(['one' => 1, 'two' => 2] as $seq => $num) {
                $cFlid = slugFormat($field[$seq]['name'], $form->project_id, $form->id);
                $cNewFlid = slugFormat(trim($request->{'cfname' . $num}), $form->project_id, $form->id);
                if($cFlid != $cNewFlid) {
                    $form->updateSubField($flid, $cFlid, $cNewFlid);
                    $field[$seq]['flid'] = $cNewFlid;
                    $field[$seq]['name'] = trim($request->{'cfname' . $num});
                } else {
                    $form->updateSubField($flid, $cFlid);
                }
            }
        }

        //Need to reindex the field if the name has changed. This will also update the column name.
        if($newFlid!=$flid) {
            $form->updateField($flid, $field, $newFlid, $comboPrefix);
        } else {
            $form->updateField($flid, $field);
        }

        //A field has been changed, so current record rollbacks become invalid.
        RevisionController::wipeRollbacks($fid);

        return redirect('projects/'.$pid.'/forms/'.$fid)->with('k3_global_success', 'field_updated');
    }

    /**
     * Update the options for a particular field.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $flid - Field ID
     * @param  Request $request
     * @return JsonResponse
     */
    public function updateFlag($pid, $fid, $flid, Request $request) {
        if(!self::validProjFormField($pid, $fid, $flid))
            return response()->json(["status"=>false,"message"=>"field_invalid"],500);

        if(!self::checkPermissions($fid, 'edit'))
            return redirect('projects/'.$pid.'/forms/'.$fid.'/fields')->with('k3_global_error', 'cant_edit_field');

        $field = self::getField($flid,$fid);
        $form = FormController::getForm($fid);
        $flag = $request->flag;
        $value = $request->value;

        switch($flag) {
            case "required":
                $field['required'] = $value;
                break;
            case "searchable":
                $field['searchable'] = $value;
                break;
            case "advsearch":
                $field['advanced_search'] = $value;
                break;
            case "extsearch":
                $field['external_search'] = $value;
                break;
            case "viewable":
                $field['viewable'] = $value;
                break;
            case "viewresults":
                $field['viewable_in_results'] = $value;
                break;
            case "extview":
                $field['external_view']= $value;
                break;
            default:
                return response()->json(["status"=>false,"message"=>"invalid_field_flag"],500);
        }

        $form->updateField($flid,$field);

        return response()->json(["status"=>true,"message"=>"field_flag_updated"],200);
    }

    /**
     * Delete a field model.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $flid - Field ID
     */
	public function destroy($pid, $fid, $flid, Request $request) {
        if(!self::validProjFormField($pid, $fid, $flid))
            return redirect()->action('FormController@show', ['pid' => $pid, 'fid' => $fid])->with('k3_global_error', 'field_invalid');

        if(!self::checkPermissions($fid, 'delete'))
            return redirect()->action('FormController@show', ['pid' => $pid, 'fid' => $fid])->with('k3_global_error', 'cant_delete_field');

        $form = FormController::getForm($fid);
        $form->deleteField($flid);

        //A field has been changed, so current record rollbacks become invalid.
        RevisionController::wipeRollbacks($fid);

        if(isset($request->redirect_route))
            return redirect('projects/'.$pid.'/forms/'.$fid)->with('k3_global_success', 'field_deleted');
        else
            return response()->json(["status"=>true, "message"=>"deleted"], 200);
	}

    /**
     * Validates a field and its basic options.
     *
     * @return JsonResponse
     */
    public function validateFieldFields(FieldRequest $request) {
        //Note:: This does work. The FieldRequest class validates the field itself, and if we get here, we return all clear!
        return response()->json(["status"=>true, "message"=>"Form Valid", 200]);
    }

    /**
     * Get a field from the database with either the flid or the slug.
     *
     * @param  string $flid - The slug of the field
     * @param  int $fid - Form ID
     * @return array - The represented field
     */
    public static function getField($flid, $fid) {
        $form = FormController::getForm($fid);
        $layout = $form->layout;

        if(array_key_exists($flid,$layout['fields']))
            return $layout['fields'][$flid];

        return null;
    }

    /**
     * Validates the project/form/field ID pairs.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $flid - Field ID
     * @return bool - The validity of the IDs
     */
    public static function validProjFormField($pid, $fid, $flid) {
        $field = self::getField($flid,$fid);

        if(!FormController::validProjForm($pid, $fid))
            return false;

        if(is_null($field))
            return false;

        return true;
    }

    /**
     * Checks a users permissions to be able to create and manipulate fields in a form.
     *
     * @param  int $fid - Form ID
     * @param  string $permission - Permission to check for
     * @return bool - Has the permission
     */
    public static function checkPermissions($fid, $permission='') {
        switch($permission) {
            case 'create':
                if(!(\Auth::user()->canCreateFields(FormController::getForm($fid))))
                    return false;
                break;
            case 'edit':
                if(!(\Auth::user()->canEditFields(FormController::getForm($fid))))
                    return false;
                break;
            case 'delete':
                if(!(\Auth::user()->canDeleteFields(FormController::getForm($fid))))
                    return false;
                break;
            default:
                if(!(\Auth::user()->inAFormGroup(FormController::getForm($fid))))
                    return false;
                break;
        }

        return true;
    }

    /**
     * View single image/video/audio/document from a record.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  int $rid - Record ID
     * @param  int $flid - Field ID
     * @param  string $filename - Image filename
     * @return Redirect
     */
    public function singleModel($pid, $fid, $rid, $flid) {
        $form = FormController::getForm($fid);
        $field = self::getField($flid, $fid);
        $record = RecordController::getRecord($pid.'-'.$fid.'-'.$rid);
        $value = $record->{$flid};
        $typedField = $form->getFieldModel($field['type']);

        return view('fields.singleModel', compact('field', 'value', 'record', 'typedField', 'flid', 'rid'));
    }
}
