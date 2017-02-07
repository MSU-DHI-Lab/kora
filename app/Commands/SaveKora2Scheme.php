<?php

namespace App\Commands;

use App\AssociatorField;
use App\ComboListField;
use App\Commands\Command;
use App\DateField;
use App\DocumentsField;
use App\Field;
use App\Form;
use App\GalleryField;
use App\GeneratedListField;
use App\GeolocatorField;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\RecordPresetController;
use App\ListField;
use App\ModelField;
use App\MultiSelectListField;
use App\PlaylistField;
use App\Record;
use App\RecordPreset;
use App\RichTextField;
use App\ScheduleField;
use App\TextField;
use App\User;
use App\VideoField;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaveKora2Scheme extends CommandKora2 implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public function handle() {
        //connect to db and set up variables
        $con = mysqli_connect($this->dbInfo['host'],$this->dbInfo['user'],$this->dbInfo['pass'],$this->dbInfo['name']);
        $newForm = $this->form;
        $oldPid = $this->pairArray[$this->sid];
        $nodes = array();
        $oldControlInfo = array();
        $numRecords = $con->query("select distinct id from p".$oldPid."Data where schemeid=".$this->sid)->num_rows;

        $table_array = $this->makeBackupTableArray($numRecords);
        if($table_array == false) { return;}
        Log::info("Started creating records for ".$this->form->slug.".");

        $row_id = DB::table('exodus_partial_progress')->insertGetId(
            $table_array
        );

        //build nodes based off of collections
        $colls = $con->query("select * from collection where schemeid=".$this->sid." order by sequence");
        while ($c = $colls->fetch_assoc()) {
            $coll = array();
            $coll['id'] = $c['collid'];
            $coll['name'] = $c['name'];
            $coll['fields'] = array();
            array_push($nodes,$coll);
        }

        //build all the fields for the form
        $controls = $con->query("select * from p".$oldPid."Control where schemeid=".$this->sid." order by sequence");
        while ($c = $controls->fetch_assoc()) {
            if($c['name'] != 'systimestamp' && $c['name'] != 'recordowner') {
                $type = $c['type'];
                $collid = $c['collid'];
                $desc = $c['description'];
                $req = $c['required'];
                $search = $c['searchable'];
                $showresults = $c['showInResults'];
                $options = $c['options'];
                ///CHECKS TO CLEAN UP XML ISSUES FROM OLD KORA
                $options = str_replace(' & ','&amp;',$options);
                //////////////////////////////////////////////
                if($options==''){$blankOpts=true;}else{$blankOpts=false;}
                $optXML = simplexml_load_string(utf8_encode($options));
                $newOpts = '';
                $newDef = '';
                $newType = '';

                switch($type) {
                    case 'TextControl':
                        if (!$blankOpts)
                            $def = $optXML->defaultValue->__toString();
                        else
                            $def = '';
                        if (!$blankOpts)
                            $textType = $optXML->textEditor->__toString();
                        else
                            $textType = 'plain';
                        if ($textType == 'plain') {
                            if (!$blankOpts)
                                $regex = $optXML->regex->__toString();
                            else
                                $regex = '';
                            if (!$blankOpts)
                                $rows = (int)$optXML->rows;
                            else
                                $rows = 1;
                            $multiline = 0;
                            if ($rows > 1)
                                $multiline = 1;

                            $newOpts = "[!Regex!]" . $regex . "[!Regex!][!MultiLine!]" . $multiline . "[!MultiLine!]";
                            $newDef = $def;
                            $newType = "Text";
                        } else if ($textType == 'rich') {
                            $newOpts = "";
                            $newDef = $def;
                            $newType = "Rich Text";
                        }
                        break;
                    case 'MultiTextControl':
                        if (!$blankOpts)
                            $def = (array)$optXML->defaultValue->value;
                        else
                            $def = array();
                        $defOpts = '';
                        if (isset($def[0])) {
                            $defOpts = $def[0];
                            for ($i = 1; $i < sizeof($def); $i++) {
                                $defOpts .= '[!]' . $def[$i];
                            }
                        }
                        if (!$blankOpts)
                            $regex = $optXML->regex->__toString();
                        else
                            $regex = '';

                        $newOpts = "[!Regex!]" . $regex . "[!Regex!][!Options!]" . $defOpts . "[!Options!]";
                        $newDef = $defOpts;
                        $newType = "Generated List";
                        break;
                    case 'DateControl':
                        if (!$blankOpts) {
                            $startY = (int)$optXML->startYear;
                            $endY = (int)$optXML->endYear;
                            $era = $optXML->era->__toString();
                            $format = $optXML->displayFormat->__toString();
                            $defYear = (int)$optXML->defaultValue->year;
                            $defMon = (int)$optXML->defaultValue->month;
                            $defDay = (int)$optXML->defaultValue->day;
                            $prefix = $optXML->prefixes->__toString();
                        }else{
                            $startY = 1900;
                            $endY = 2020;
                            $era = 'No';
                            $format = 'MMDDYYYY';
                            $defYear = '';
                            $defMon = '';
                            $defDay = '';
                            $prefix = 'No';
                        }
                        $circa = 'No';
                        $for = 'MMDDYYYY';
                        if($prefix=="circa"){$circa="Yes";}
                        if($format=="MDY"){$for="MMDDYYYY";}
                        else if($format=="DMY"){$for="DDMMYYYY";}
                        else if($format=="YMD"){$for="YYYYMMDD";}

                        $newOpts = "[!Circa!]".$circa."[!Circa!][!Start!]".$startY."[!Start!][!End!]".$endY."[!End!][!Format!]".$for."[!Format!][!Era!]".$era."[!Era!]";
                        $newDef = "[M]".$defMon."[M][D]".$defDay."[D][Y]".$defYear."[Y]";
                        $newType = "Date";
                        break;
                    case 'MultiDateControl':
                        if (!$blankOpts) {
                            $startY = (int)$optXML->startYear;
                            $endY = (int)$optXML->endYear;
                            $def = (array)$optXML->defaultValue;
                        }
                        else {
                            $startY = 1990;
                            $endY = 2020;
                            $def = array();
                        }

                        if(isset($def["date"]))
                            $def = $def["date"];
                        else{
                            $def=array();
                        }
                        $defOpts = '';
                        if(isset($def[0])) {
                            $defOpts = "Event 1: " . $def[0]->month . "/" . $def[0]->day . "/" . $def[0]->year . " - " . $def[0]->month . "/" . $def[0]->day . "/" . $def[0]->year;
                            for ($i = 1; $i < sizeof($def); $i++) {
                                $defOpts .= '[!]' . "Event " . ($i + 1) . ": " . $def[$i]->month . "/" . $def[$i]->day . "/" . $def[$i]->year . " - " . $def[$i]->month . "/" . $def[$i]->day . "/" . $def[$i]->year;
                            }
                        }

                        $newOpts = "[!Start!]".$startY."[!Start!][!End!]".$endY."[!End!][!Calendar!]No[!Calendar!]";
                        $newDef = $defOpts;
                        $newType = "Schedule";
                        break;
                    case 'FileControl':
                        if (!$blankOpts)
                            $maxSize = (int)$optXML->maxSize;
                        else
                            $maxSize=0;
                        if (!$blankOpts)
                            $allowed = (array)$optXML->allowedMIME->mime;
                        else
                            $allowed=array();
                        $allOpts = '';
                        if(isset($allowed[0])) {
                            $allOpts = $allowed[0];
                            for ($i = 1; $i < sizeof($allowed); $i++) {
                                $allOpts .= '[!]' . $allowed[$i];
                            }
                        }

                        $newOpts = "[!FieldSize!]".$maxSize."[!FieldSize!][!MaxFiles!]0[!MaxFiles!][!FileTypes!]".$allOpts."[!FileTypes!]";
                        $newType = "Documents";
                        break;
                    case 'ImageControl':
                        if (!$blankOpts)
                            $maxSize = (int)$optXML->maxSize;
                        else
                            $maxSize=0;
                        if (!$blankOpts)
                            $allowed = (array)$optXML->allowedMIME->mime;
                        else
                            $allowed=array();
                        $allOpts = '';
                        if(isset($allowed[0])) {
                            $allOpts = $allowed[0];
                            for ($i = 1; $i < sizeof($allowed); $i++) {
                                if ($allowed[$i] != "image/pjpeg" && $allowed[$i] != "image/x-png")
                                    $allOpts .= '[!]' . $allowed[$i];
                            }
                        }
                        $thumbW = (int)$optXML->thumbWidth;
                        $thumbH = (int)$optXML->thumbHeight;

                        $newOpts = "[!FieldSize!]".$maxSize."[!FieldSize!][!ThumbSmall!]".$thumbW."x".$thumbH."[!ThumbSmall!][!ThumbLarge!]".($thumbW*2)."x".($thumbH*2)."[!ThumbLarge!][!MaxFiles!]0[!MaxFiles!][!FileTypes!]".$allOpts."[!FileTypes!]";
                        $newType = "Gallery";
                        break;
                    case 'ListControl':
                        if (!$blankOpts)
                            $opts = (array)$optXML->option;
                        else
                            $opts = array();
                        $allOpts = '';
                        if(isset($opts[0])) {
                            $allOpts = $opts[0];
                            for ($i = 1; $i < sizeof($opts); $i++) {
                                $allOpts .= '[!]' . $opts[$i];
                            }
                        }
                        if (!$blankOpts)
                            $def = $optXML->defaultValue->__toString();
                        else
                            $def = '';

                        $newOpts = "[!Options!]".$allOpts."[!Options!]";
                        $newDef = $def;
                        $newType = "List";
                        break;
                    case 'MultiListControl':
                        if (!$blankOpts)
                            $opts = (array)$optXML->option;
                        else
                            $opts = array();
                        $allOpts = '';
                        if(isset($opts[0])) {
                            $allOpts = $opts[0];
                            for ($i = 1; $i < sizeof($opts); $i++) {
                                $allOpts .= '[!]' . $opts[$i];
                            }
                        }
                        if (!$blankOpts)
                            $def = (array)$optXML->defaultValue->option;
                        else
                            $def = array();
                        $defOpts = '';
                        if(isset($def[0])) {
                            $defOpts = $def[0];
                            for ($i = 1; $i < sizeof($def); $i++) {
                                $defOpts .= '[!]' . $def[$i];
                            }
                        }

                        $newOpts = "[!Options!]".$allOpts."[!Options!]";
                        $newDef = $defOpts;
                        $newType = "Multi-Select List";
                        break;
                    case 'AssociatorControl':
                        $newOpts = "[!SearchForms!][!SearchForms!]";
                        $newType = "Associator";
                        break;
                }

                //save it
                $field = new Field();
                $field->pid = $newForm->pid;
                $field->fid = $newForm->fid;
                $field->type = $newType;
                $field->name = $c['name'];
                $slug = str_replace(' ','_',$c['name']);
                while(Field::slugExists($slug)){
                    $slug .= $this->fieldSlugGenerator();
                }
                $field->slug = $slug;
                $field->desc = $desc;
                $field->required = $req;
                $field->searchable = $search;
                $field->extsearch = $search;
                $field->viewable = $showresults;
                $field->viewresults = $showresults;
                $field->extview = $showresults;
                $field->default = $newDef;
                $field->options = $newOpts;
                $field->save();

                $oldControlInfo[$c['cid']] = $field->flid;

                //place field in appropriate node
                foreach($nodes as $key => $node){
                    if($collid == $node['id']) {
                        $collFields = $node['fields'];
                        array_push($collFields, $field->flid);
                        $nodes[$key]['fields'] = $collFields;
                    }
                }
            }
        }

        //update form layout
        $newLay = '<LAYOUT>';
        foreach($nodes as $node){
            $newLay .= "<NODE title='".$node['name']."'>";
            foreach($node['fields'] as $fid){
                $newLay .= '<ID>'.$fid.'</ID>';
            }
            $newLay .= '</NODE>';
        }
        $newLay .= '</LAYOUT>';
        $newForm->layout = $newLay;
        $newForm->save();

        //time to build the records
        Log::info('Iterating through data');

        //Record stuff//////////////////////////////////////////
        $records = $con->query("select * from p".$oldPid."Data where schemeid=".$this->sid);
        $oldKidToNewRid = array();

        while ($r = $records->fetch_assoc()) {
            if(!array_key_exists($r['id'],$oldKidToNewRid)) {
                $recModel = new Record();
                $recModel->pid = $newForm->pid;
                $recModel->fid = $newForm->fid;
                $recModel->save();

                //increment table
                DB::table("exodus_partial_progress")->where("id", $row_id)->increment("progress", 1, ["updated_at" => Carbon::now()]);

                $oldKidToNewRid[$r['id']] = $recModel->rid;
            }else{
                $recModel = RecordController::getRecord($oldKidToNewRid[$r['id']]);
            }

            if($r['cid']==1){
                continue; //we don't want to save the timestamp
            }
            //get the original record owner for some consistency, defaults to current user
            else if($r['cid']==2){
                $email = '';
                $equery = $con->query("select email from user where username='".$r['value']."'");
                if(!$equery){
                    //if we get here, it's most likely an old project/scheme where the record owner is not in control 2
                    $recModel->owner = 1;
                    $recModel->save();
                    continue;
                }
                while($e = $equery->fetch_assoc()){
                    $email = $e['email'];
                }
                $newUser = User::where('email','=',$email)->first();
                if(!is_null($newUser)){
                    $recModel->owner = $newUser->id;
                }else{
                    $recModel->owner = 1;
                }
                $recModel->save();
            }
            else {
                //make sure the control was converted
                if(!isset($oldControlInfo[$r['cid']])){continue;}
                $flid = $oldControlInfo[$r['cid']];
                $field = FieldController::getField($flid);
                $value = $r['value'];

                switch($field->type) {
                    case 'Text':
                        $text = new TextField();
                        $text->rid = $recModel->rid;
                        $text->fid = $recModel->fid;
                        $text->flid = $field->flid;
                        $text->text = $value;
                        $text->save();

                        break;
                    case 'Rich Text':
                        $rich = new RichTextField();
                        $rich->rid = $recModel->rid;
                        $rich->fid = $recModel->fid;
                        $rich->flid = $field->flid;
                        $rich->rawtext = $value;
                        $rich->save();

                        break;
                    case 'Generated List':
                        $mtc = (array)simplexml_load_string(utf8_encode($value))->text;
                        $optStr = implode('[!]',$mtc);

                        $gen = new GeneratedListField();
                        $gen->rid = $recModel->rid;
                        $gen->fid = $recModel->fid;
                        $gen->flid = $field->flid;
                        $gen->options = $optStr;
                        $gen->save();

                        break;
                    case 'Date':
                        $dateXML = simplexml_load_string(utf8_encode($value));
                        $circa=0;
                        if((string)$dateXML->prefix == 'circa'){
                            $circa=1;
                        }
                        $era = 'CE';
                        if(FieldController::getFieldOption($field,'Era')=='Yes'){
                            $era = (string)$dateXML->era;
                        }

                        $date = new DateField();
                        $date->rid = $recModel->rid;
                        $date->fid = $recModel->fid;
                        $date->flid = $field->flid;
                        $date->circa = $circa;
                        $date->month = (int)$dateXML->month;
                        $date->day = (int)$dateXML->day;
                        $date->year = (int)$dateXML->year;
                        $date->era = $era;
                        $date->save();

                        break;
                    case 'Schedule':
                        $mlc = (array)simplexml_load_string(utf8_encode($value))->date;
                        $formattedDates = array();
                        $i=1;

                        foreach($mlc as $date){
                            $m = (int)$date->month;
                            $d = (int)$date->day;
                            $y = (int)$date->year;
                            $dateStr = 'Event '.$i.': '.$m.'/'.$d.'/'.$y.' - '.$m.'/'.$d.'/'.$y;
                            array_push($formattedDates,$dateStr);
                            $i++;
                        }

                        $eventStr = implode('[!]',$formattedDates);

                        $sched = new ScheduleField();
                        $sched->rid = $recModel->rid;
                        $sched->fid = $recModel->fid;
                        $sched->flid = $field->flid;
                        $sched->events = $eventStr;
                        $sched->save();

                        break;
                    case 'Documents':
                        $fileXML = simplexml_load_string(utf8_encode($value));
                        $realname = (string)$fileXML->originalName;
                        $localname = (string)$fileXML->localName;

                        if($localname!='') {
                            $docs = new DocumentsField();
                            $docs->rid = $recModel->rid;
                            $docs->fid = $recModel->fid;
                            $docs->flid = $field->flid;

                            //Make folder
                            $newPath = env('BASE_PATH') . 'storage/app/files/p' . $newForm->pid . '/f' . $newForm->fid . '/r' . $recModel->rid . '/fl' . $field->flid.'/';
                            mkdir($newPath, 0775, true);

                            $oldDir = $this->filePath.'/'.$oldPid.'/'.$this->sid.'/';

                            if(!file_exists($oldDir.$localname)){
                                //OLD FILE DOESNT EXIST SO BALE
                                continue;
                            }

                            //Move file TODO
                            //rename($oldDir.$localname,$newPath.$realname);

                            //Get file info
                            $mimes = DocumentsField::getMimeTypes();
                            $ext = pathinfo($newPath.$realname,PATHINFO_EXTENSION);
                            if (!array_key_exists($ext, $mimes))
                                $type = 'application/octet-stream';
                            else
                                $type = $mimes[$ext];

                            $name = '[Name]'.$realname.'[Name]';
                            $size = '[Size]'.filesize($newPath.$realname).'[Size]';
                            $typeS = '[Type]'.$type.'[Type]';
                            //Build file string
                            $info = $name.$size.$typeS;
                            $docs->documents = $info;
                            $docs->save();
                        }
                        break;
                    case 'Gallery':
                        $fileXML = simplexml_load_string(utf8_encode($value));
                        $realname = (string)$fileXML->originalName;
                        $localname = (string)$fileXML->localName;

                        if($localname!='') {
                            $gal = new GalleryField();
                            $gal->rid = $recModel->rid;
                            $gal->fid = $recModel->fid;
                            $gal->flid = $field->flid;

                            //Make folder
                            $newPath = env('BASE_PATH') . 'storage/app/files/p' . $newForm->pid . '/f' . $newForm->fid . '/r' . $recModel->rid . '/fl' . $field->flid.'/';
                            $newPathM = $newPath.'medium/';
                            $newPathT = $newPath.'thumbnail/';
                            mkdir($newPath, 0775, true);
                            mkdir($newPathM, 0775, true);
                            mkdir($newPathT, 0775, true);

                            $oldDir = $this->filePath.'/'.$oldPid.'/'.$this->sid.'/';

                            if(!file_exists($oldDir.$localname)){
                                //OLD FILE DOESNT EXIST SO BALE
                                continue;
                            }

                            //Move files
                            //rename($oldDir.$localname,$newPath.$realname);

                            //Create thumbs
                            $smallParts = explode('x',FieldController::getFieldOption($field,'ThumbSmall'));
                            $largeParts = explode('x',FieldController::getFieldOption($field,'ThumbLarge'));
                            $thumb = true;
                            $medium = true;
                            try {
                                $tImage = new \Imagick($newPath . $realname);
                            } catch(\ImagickException $e){
                                $thumb = false;
                                Log::info("Issue creating thumbnail for record ".$recModel->rid.".");
                            }
                            try {
                                $mImage = new \Imagick($newPath . $realname);
                            } catch(\ImagickException $e){
                                $medium = false;
                                Log::info("Issue creating medium thumbnail for record ".$recModel->rid.".");
                            }

                            //Size check
                            if($smallParts[0]==0 | $smallParts[1]==0){
                                $smallParts[0] = 150;
                                $smallParts[1] = 150;
                            }
                            if($largeParts[0]==0 | $largeParts[1]==0){
                                $largeParts[0] = 300;
                                $largeParts[1] = 300;
                            }

                            if($thumb){
                                $tImage->thumbnailImage($smallParts[0],$smallParts[1],true);
                                $tImage->writeImage($newPathT.$realname);
                            }
                            if($medium){
                                $mImage->thumbnailImage($largeParts[0],$largeParts[1],true);
                                $mImage->writeImage($newPathM.$realname);
                            }

                            //Get file info
                            $mimes = DocumentsField::getMimeTypes();
                            $ext = pathinfo($newPath.$realname,PATHINFO_EXTENSION);
                            if (!array_key_exists($ext, $mimes))
                                $type = 'application/octet-stream';
                            else
                                $type = $mimes[$ext];

                            $name = '[Name]'.$realname.'[Name]';
                            $size = '[Size]'.filesize($newPath.$realname).'[Size]';
                            $typeS = '[Type]'.$type.'[Type]';
                            //Build file string
                            $info = $name.$size.$typeS;
                            $gal->images = $info;
                            $gal->save();
                        }
                        break;
                    case 'List':
                        $list = new ListField();
                        $list->rid = $recModel->rid;
                        $list->fid = $recModel->fid;
                        $list->flid = $field->flid;
                        $list->option = $value;
                        $list->save();

                        break;
                    case 'Multi-Select List':
                        $mlc = (array)simplexml_load_string(utf8_encode($value))->value;
                        $optStr = implode('[!]',$mlc);

                        $msl = new MultiSelectListField();
                        $msl->rid = $recModel->rid;
                        $msl->fid = $recModel->fid;
                        $msl->flid = $field->flid;
                        $msl->options = $optStr;
                        $msl->save();

                        break;
                }
            }
        }

        //Last but not least, record presets!!!!!!!!!
        //TODO: I don't think were passing the right IDs
        $recordPresets = $records = $con->query("select * from recordPreset where schemeid=".$this->sid);
        while ($rp = $recordPresets->fetch_assoc()) {
            $preset = new RecordPreset();
            $preset->rid = $oldKidToNewRid[$rp['kid']];
            $preset->fid = $newForm->fid;
            $preset->name = $rp['name'];

            $preset->save();

            $preset->preset = json_encode($this->getRecordArray($preset->rid, $preset->id));
            $preset->save();
        }

        //End Record stuff//////////////////////////////////////

        //Breath now
        Log::info("Done creating records for ".$this->form->slug.".");
        DB::table("exodus_overall_progress")->where("id", $this->exodus_id)->increment("progress",1,["updated_at"=>Carbon::now()]);

        mysqli_close($con);
    }

    private function fieldSlugGenerator()
    {
        $valid = '0123456789';

        $password = '';
        for ($i = 0; $i < 4; $i++){
            $password .= $valid[( rand() % 10 )];
        }
        return $password;
    }

    /**
     * Builds an array representing a record, saving its FLIDs for creation page population.
     *
     * @param $rid, the record's id.
     * @return mixed
     */
    public function getRecordArray($rid, $id)
    {
        $record = Record::where('rid', '=', $rid)->first();
        $form = Form::where('fid', '=', $record->fid)->first();

        $field_collect = $form->fields()->get();
        $field_array = array();
        $flid_array = array();

        $fileFields = false; // Does the record have any file fields?

        foreach($field_collect as $field) {
            $data = array();
            $data['flid'] = $field->flid;
            $data['type'] = $field->type;

            switch ($field->type) {
                case 'Text':
                    $textfield = TextField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($textfield->text)) {
                        $data['text'] = $textfield->text;
                    }
                    else {
                        $data['text'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Rich Text':
                    $rtfield = RichTextField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($rtfield->rawtext)) {
                        $data['rawtext'] = $rtfield->rawtext;
                    }
                    else {
                        $data['rawtext'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Number':
                    $numberfield = NumberField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($numberfield->number)) {
                        $data['number'] = $numberfield->number;
                    }
                    else {
                        $data['number'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'List':
                    $listfield = ListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($listfield->option)) {
                        $data['option'] = $listfield->option;
                    }
                    else {
                        $data['option'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Multi-Select List':
                    $mslfield = MultiSelectListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($mslfield->options)) {
                        $data['options'] = explode('[!]', $mslfield->options);
                    }
                    else {
                        $data['options'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Generated List':
                    $gnlfield = GeneratedListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($gnlfield->options)) {
                        $data['options'] = explode('[!]', $gnlfield->options);
                    }
                    else {
                        $data['options'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Date':
                    $datefield = DateField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if(!empty($datefield->circa)) {
                        $date_array['circa'] = $datefield->circa;
                    }
                    else {
                        $date_array['circa'] = null;
                    }

                    if(!empty($datefield->era)) {
                        $date_array['era'] = $datefield->era;
                    }
                    else {
                        $date_array['era'] = null;
                    }

                    if(!empty($datefield->day)) {
                        $date_array['day'] = $datefield->day;
                    }
                    else {
                        $date_array['day'] = null;
                    }

                    if(!empty($datefield->month)) {
                        $date_array['month'] = $datefield->month;
                    }
                    else {
                        $date_array['month'] = null;
                    }

                    if(!empty($datefield->year)) {
                        $date_array['year'] = $datefield->year;
                    }
                    else {
                        $date_array['year'] = null;
                    }

                    $data['data'] = $date_array;
                    $flid_array[] = $field->flid;
                    break;

                case 'Schedule':
                    $schedfield = ScheduleField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if(!empty($schedfield->events)) {
                        $data['events'] = explode('[!]', $schedfield->events);
                    }
                    else {
                        $data['events'] = null;
                    }


                    $flid_array[] = $field->flid;
                    break;

                case 'Geolocator':
                    $geofield = GeolocatorField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($geofield->locations)) {
                        $data['locations'] = explode('[!]', $geofield->locations);
                    }
                    else {
                        $data['locations'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Combo List':
                    $cmbfield = ComboListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($cmbfield->options)) {
                        $data['combolists'] = explode('[!val!]', $cmbfield->options);
                    }
                    else {
                        $data['combolists'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                case 'Documents':
                    $docfield = DocumentsField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($docfield->documents)) {
                        $data['documents'] = explode('[!]', $docfield->documents);
                    }
                    else {
                        $data['documents'] = null;
                    }

                    $flid_array[] = $field->flid;
                    $fileFields = true;
                    break;

                case 'Gallery':
                    $galfield = GalleryField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($galfield->images)) {
                        $data['images'] = explode('[!]', $galfield->images);
                    }
                    else {
                        $data['images'] = null;
                    }

                    $flid_array[] = $field->flid;
                    $fileFields = true;
                    break;

                case 'Playlist':
                    $playfield = PlaylistField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($playfield->audio)) {
                        $data['audio'] = explode('[!]', $playfield->audio);
                    }
                    else {
                        $data['audio'] = null;
                    }

                    $flid_array[] = $field->flid;
                    $fileFields = true;
                    break;

                case 'Video':
                    $vidfield = VideoField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($vidfield->video)) {
                        $data['video'] = explode('[!]', $vidfield->video);
                    }
                    else {
                        $data['video'] = null;
                    }

                    $flid_array[] = $field->flid;
                    $fileFields = true;
                    break;

                case '3D-Model':
                    $modelfield = ModelField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($modelfield->model)) {
                        $data['model'] = $modelfield->model;
                    }
                    else {
                        $data['model'] = null;
                    }

                    $flid_array[] = $field->flid;
                    $fileFields = true;
                    break;

                case 'Associator':
                    $assocfield = AssociatorField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->first();

                    if (!empty($assocfield->records)) {
                        $data['records'] = explode('[!]', $assocfield->records);
                    }
                    else {
                        $data['records'] = null;
                    }

                    $flid_array[] = $field->flid;
                    break;

                default:
                    // None other supported right now, though this list should be exhaustive.
                    break;
            }

            $field_array[$field->flid] = $data;
        }

        // A file field was in use, so we need to move the record files to a preset directory.
        if ($fileFields) {
            $this->moveFilesToPreset($record->rid, $id);
        }

        $response['data'] = $field_array;
        $response['flids'] = $flid_array;
        return $response;
    }

    public function moveFilesToPreset($rid, $id) {
        $presets_path = env('BASE_PATH').'storage/app/presetFiles';

        //
        // Create the presets file path if it does not exist.
        //
        if(!is_dir($presets_path)) {
            mkdir($presets_path, 0755, true);
        }

        $path = $presets_path . '/preset' . $id; // Path for the new preset's directory.

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Build the record's directory.
        $record = RecordController::getRecord($rid);

        $record_path = env('BASE_PATH') . 'storage/app/files/p' . $record->pid . '/f' . $record->fid . '/r' . $record->rid;

        //
        // Recursively copy the record's file directory.
        //
        RecordPresetController::recurse_copy($record_path, $path);
    }
}