<?php

/**
* Hyphenates a string
*
* @return string - hyphenated
*/
function str_hyphenated($string) {
    return strtolower(preg_replace("/[^\w]+/", "-", $string));
}

/**
 * Gets the available set of languages in the installation.
 *
 * @return array - the languages
 */
 function getLangs() {
     return \Illuminate\Support\Facades\Config::get('app.locales_supported');
 }

/**
 * Checks to see if kora is installed.
 *
 * @return bool - is installed
 */
function databaseConnectionExists() {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Exception $e) {
        return false;
    }
    return \Illuminate\Support\Facades\Schema::hasTable('users');
}

/**
 * Returns array of links
 *
 * @return array - the links
 */
function getDashboardBlockLink($block, $link_type) {
    switch ($block->type) {
        case 'Project':
            return getDashboardProjectBlockLink($block, $link_type);
            break;
        case 'Form':
            return getDashboardFormBlockLink($block, $link_type);
            break;
        default:
          return [];
    }
}

/**
 * Converts the directory size in bytes to the most readable form.
 *
 * @param  int $bytes - Size in bytes
 * @return string - The readable size value
 */
function fileSizeConvert($bytes) {
    $result = "0 B";
    $bytes = floatval($bytes);
    $arBytes = array(
        0 => array(
            "UNIT" => "TB",
            "VALUE" => pow(1024, 4)
        ),
        1 => array(
            "UNIT" => "GB",
            "VALUE" => pow(1024, 3)
        ),
        2 => array(
            "UNIT" => "MB",
            "VALUE" => pow(1024, 2)
        ),
        3 => array(
            "UNIT" => "KB",
            "VALUE" => 1024
        ),
        4 => array(
            "UNIT" => "B",
            "VALUE" => 1
        ),
    );

    foreach($arBytes as $arItem) {
        if($bytes >= $arItem["VALUE"]) {
            $result = $bytes / $arItem["VALUE"];
            $result = strval(round($result, 2))." ".$arItem["UNIT"];
            break;
        }
    }
    return $result;
}

/**
 * Maps a field name to its internal FLID.
 *
 * @param  string $name - Name of field
 * @param  int $pid - Project ID
 * @param  int $fid - Form ID
 * @return string - The FLID
 */
function fieldMapper($name, $pid, $fid) {
    return str_replace(' ','_',$name).'_'.$pid.'_'.$fid.'_';
}

/**
 * Returns string in slug format
 *
 * @return string - slug
 */
function slugFormat($name, $project_id, $form_id) {
  return str_replace(" ","_", $name) . '_' . $project_id . '_' . $form_id . '_';
}

/**
 * Updates a global timer.
 *
 * @param  string $name - Name of timer to update
 */
function updateGlobalTimer($name) {
    $timer = \App\Timer::where('name','=',$name)->first();
    $timer->timestamps = false;
    $timer->interval = \Carbon\Carbon::now();
    $timer->save();
}

/**
 * Checks if we should remind an admin to update the reverse cache table.
 * If it has been 7 days since cache was built, and last record update after last cache update
 *
 * @return string - The FLID
 */
function checkAssocCacheState() {
    $user = Auth::user();
    if(is_null($user) || !$user->admin)
        return false;

    $cacheTimer = \App\Timer::where('name','=','reverse_assoc_cache_build')->first();
    $cache = \Carbon\Carbon::parse($cacheTimer->interval);
    $recordTimer = \App\Timer::where('name','=','last_record_updated')->first();
    $record = \Carbon\Carbon::parse($recordTimer->interval);
    $now = \Carbon\Carbon::now();

    $daysSinceCache = $cache->diffInDays($now,false);
    $recordsUpdatedSinceCache = $cache->diffInDays($record,false);

    if($daysSinceCache >= 7 && $recordsUpdatedSinceCache > 0)
        return true;

    return false;
}

/**
 * Returns array of links
 *
 * @return array - the links
 */
function getDashboardProjectBlockLink($block, $link_type) {
  $options = json_decode($block->options, true);
  switch ($link_type) {
      case 'edit':
          return [
            'tooltip' => 'Edit Project',
            'icon-class' => 'icon-edit-little',
            'href' => action('ProjectController@edit', ['projects'=>$options['pid']]),
			'type' => 'edit'
          ];
          break;
      case 'search':
          return [
            'tooltip' => 'Search Project Records',
            'icon-class' => 'icon-search',
            'href' => action('ProjectSearchController@keywordSearch', ['pid'=>$options['pid']]),
			'type' => 'search'
          ];
          break;
      case 'form-import':
          return [
            'tooltip' => 'Import Form',
            'icon-class' => 'icon-form-import-little',
            'href' => action('FormController@importFormView', ['pid'=>$options['pid']]),
			'type' => 'form-import'
          ];
          break;
      case 'form-new':
          return [
            'tooltip' => 'Create New Form',
            'icon-class' => 'icon-form-new-little',
            'href' => action('FormController@create', ['pid'=>$options['pid']]),
			'type' => 'form-new'
          ];
          break;
      case 'permissions':
          return [
            'tooltip' => 'Project Permissions',
            'icon-class' => 'icon-star',
            'href' => action('ProjectGroupController@index', ['pid'=>$options['pid']]),
			'type' => 'permissions'
          ];
          break;
      case 'presets':
          return [
            'tooltip' => 'Field Value Presets',
            'icon-class' => 'icon-preset-Little',
            'href' => action('FieldValuePresetController@index', ['pid'=>$options['pid']]),
			'type' => 'presets'
          ];
          break;
	  case 'import':
	      return [
            'tooltip' => 'Import Multi-Form Records Setup',
            'icon-class' => 'icon-importMFRecords-little',
		    'href' => url('/').'/projects/'.$options['pid'].'/importMF',
		    'type' => 'import'
		  ];
		  break;
	  case 'import2k':
          return [
            'tooltip' => 'Kora 2 Scheme Importer',
            'icon-class' => 'icon-k2SchemeImporter-little',
            'href' => url('/').'/projects/'.$options['pid'].'/forms/importk2',
            'type' => 'import2k'
          ];
          break;
	  case 'export':
	      return [
            'tooltip' => 'Export Project',
            'icon-class' => 'icon-exportProject-little',
		    'href' => action('ExportController@exportProject',['pid' => $options['pid']]),
		    'type' => 'export'
		  ];
		  break;
      default:
        return [];
  }
}

/**
 * Returns array of links
 *
 * @return array - the links
 */
function getDashboardFormBlockLink($block, $link_type) {
  $options = json_decode($block->options, true);
  $form = \App\Http\Controllers\FormController::getForm($options['fid']);
  switch ($link_type) {
      case 'edit':
          return [
            'tooltip' => 'Edit Form',
            'icon-class' => 'icon-edit-little',
            'href' => action('FormController@edit', ['pid' => $form->project_id, 'fid' => $form->id]),
			'type' => 'edit'
          ];
          break;
      case 'search':
          return [
            'tooltip' => 'Search Form Records',
            'icon-class' => 'icon-search',
            'href' => action('RecordController@index', ['pid' => $form->project_id, 'fid' => $form->id]),
			'type' => 'search'
          ];
          break;
      case 'record-new':
          return [
            'tooltip' => 'Create New Record',
            'icon-class' => 'icon-record-new-little',
            'href' => action('RecordController@create',['pid' => $form->project_id, 'fid' => $form->id]),
			'type' => 'record-new'
          ];
          break;
      case 'field-new':
          $lastPage = sizeof($form->layout["pages"])-1;
          return [
            'tooltip' => 'Create New Field',
            'icon-class' => 'icon-form-new-little',
            'href' => action('FieldController@create', ['pid'=>$form->project_id, 'fid' => $form->id, 'rootPage' => $lastPage]),
			'type' => 'field-new'
          ];
          break;
      case 'form-permissions':
          return [
            'tooltip' => 'Form Permissions',
            'icon-class' => 'icon-star',
            'href' => action('FormGroupController@index', ['pid' => $form->project_id, 'fid' => $form->id]),
			'type' => 'form-permissions'
          ];
          break;
      case 'revisions':
          return [
            'tooltip' => 'Manage Record Revisions',
            'icon-class' => 'icon-preset-Little',
            'href' => action('RevisionController@index', ['pid'=>$form->project_id, 'fid'=>$form->id]),
			'type' => 'revisions'
          ];
          break;
	  case 'import':
	      return [
            'tooltip' => 'Import Records',
            'icon-class' => 'icon-importrecords-little',
		    'href' => action('RecordController@importRecordsView', ['pid' => $form->project_id, 'fid' => $form->id]),
		    'type' => 'import'
		  ];
          break;
      case 'batch':
          return [
            'tooltip' => 'Batch Assign Field Values',
            'icon-class' => 'icon-batchAssign-little',
            'href' => action('RecordController@showMassAssignmentView',['pid' => $form->project_id, 'fid' => $form->id]),
            'type' => 'batch'
          ];
          break;
      case 'export-records':
          return [
            'tooltip' => 'Export All Records',
            'icon-class' => 'icon-exportRecords-Little',
            'href' => '#',
            'type' => 'export-records',
            'class' => 'export-record-js'
          ];
          break;
      case 'assoc-permissions':
          return [
            'tooltip' => 'Association Permissions',
            'icon-class' => 'icon-associationPermissions-little',
            'href' => action('AssociationController@index', ['fid' => $form->id, 'pid' => $form->project_id]),
            'type' => 'assoc-permissions'
          ];
          break;
      case 'export-form':
          return [
            'tooltip' => 'Export Form',
            'icon-class' => 'icon-exportForm-Little',
            'href' => action('ExportController@exportForm',['fid'=>$form->id, 'pid' => $form->project_id]),
            'type' => 'export-form'
          ];
          break;
      default:
        return [];
  }
}

/**
 * Returns array of links
 *
 * @return array - the links
 */
function getDashboardRecordBlockLink($record) {
    return array(
        [
            'tooltip' => 'Edit Record',
            'icon-class' => 'icon-edit-little',
            'href' => action('RecordController@edit', ['pid' => $record->project_id, 'fid' => $record->form_id, 'rid' => $record->id])
        ],
        [
            'tooltip' => 'Duplicate Record',
            'icon-class' => 'icon-duplicate-little',
            'href' => action('RecordController@cloneRecord', ['pid' => $record->project_id, 'fid' => $record->form_id, 'rid' => $record->id])
        ],
        [
            'tooltip' => 'View Revisions',
            'icon-class' => 'icon-clock-little',
            'href' => action('RevisionController@show', ['pid' => $record->project_id, 'fid' => $record->form_id, 'rid' => $record->id])
        ]
    );
}

function parseCSV($record) {
    $firstRow = true;
    $result = $fields = array();
    if(($handle = fopen($record, "r")) !== FALSE) {
        $bom = pack("CCC", 0xef, 0xbb, 0xbf);
        while(($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            $num = count($data);
            if($firstRow) {
                for($c=0; $c < $num; $c++) {
                    //GETS RID OF BYTE ORDER MARKS THAT ARE SOMETIMES MADE BY FGETCSV
                    if(0 === strncmp($data[$c], $bom, 3))
                        $data[$c] = substr($data[$c], 3);
                    array_push($fields, str_replace('ufeff', '', $data[$c]));
                }
                $firstRow = false;
            } else {
                $record = [];
                for($c=0; $c < $num; $c++) {
                    $record[trim($fields[$c])] = $data[$c];
                }
                $result[] = $record;
            }
        }
        fclose($handle);
    }

    return $result;
}
