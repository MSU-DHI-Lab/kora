<?php namespace App;

use App\Http\Controllers\FormController;
use App\Http\Controllers\RecordController;

class Search {

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | This class contains core search functionality in kora
    |
    */

    /**
     * @var int - At least one argument must be in some record's field
     */
    const SEARCH_OR = 0;
    /**
     * @var int - All arguments must be in a particular record's fields
     */
    const SEARCH_AND = 1;
    /**
     * @var int - Record must be a KID in this form
     */
    const SEARCH_KID = 2;
    /**
     * @var int - Id of the project we're searching in
     */
    private $pid;
    /**
     * @var int - Id of the form we're searching in
     */
    private $fid;
    /**
     * @var array - The array of keywords
     */
    private $keys;
    /**
     * @var int - Method of search, see the search operators
     */
    private $method;

    /**
     * Search constructor.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  array $keys - The query of the search
     * @param  int $method - The method of search, see search operators
     */
    public function __construct($pid, $fid, $keys, $method) {
        $this->pid = $pid;
        $this->fid = $fid;
        $this->keys = self::prepare($keys);
        $this->method = $method;
    }

    /**
     * Runs the keyword search routine on all field types.
     *
     * @param  array $fields - Field models we are searching through
     * @param  boolean $external - Is this search coming from an external source
     * @param  boolean $negative - Get opposite results of the search
     * @param  boolean $customWildcards - Do we supply wildcards, or does the user
     * @return array - Array of rids satisfying search parameters
     */
    public function formKeywordSearch($fields = null, $external = false, $negative = false, $customWildcards = false) {
        $form = FormController::getForm($this->fid);
        if(is_null($form))
            return [];
        $recordMod = new Record(array(),$this->fid);

        if(is_null($fields))
            $fields = $form->layout['fields'];
        $rids = [];

        switch($this->method) {
            case self::SEARCH_OR:
                //foreach args
                foreach($this->keys as $arg) {
                    if(!$customWildcards && $arg!="")
                        $arg = "%$arg%";

                    $searchTypes = array();

                    //search the fields
                    foreach($fields as $flid => $field) {
                        // These checks make sure the field is searchable
                        if( (!$external && $field['searchable']) || ($external && $field['external_search']) )
                            $searchTypes[$field['type']][] = $flid;
                    }

                    //This way we don't run more DB queries than the max number of available field types
                    foreach($searchTypes as $type => $flids) {
                        $results = $form->getFieldModel($type)->keywordSearchTyped($flids, $arg, $recordMod, $form, $negative);
                        $this->imitateMerge($rids, $results);
                    }
                }

                //make array unique
                $rids = array_flip(array_flip($rids));
                break;
            case self::SEARCH_AND:
                //array set
                $ridSets = array();

                //foreach args
                foreach($this->keys as $arg) {
                    if(!$customWildcards && $arg!="")
                        $arg = "%$arg%";

                    $set = array();
                    $searchTypes = array();

                    //search the fields
                    foreach($fields as $flid => $field) {
                        // These checks make sure the field is searchable
                        if( (!$external && $field['searchable']) || ($external && $field['external_search']) )
                            $searchTypes[$field['type']][] = $flid;
                    }

                    //This way we don't run more DB queries than the max number of available field types
                    foreach($searchTypes as $type => $flids) {
                        $results = $form->getFieldModel($type)->keywordSearchTyped($flids, $arg, $recordMod, $form, $negative);
                        $this->imitateMerge($set, $results);
                    }

                    //create unique set of rids
                    $set = array_flip(array_flip($set));
                    //add to array set
                    $ridSets[] = $set;
                }

                //run array intersect on the arrays
                $rids = $ridSets[0];
                for($i=1;$i<sizeof($ridSets);$i++) {
                    $rids = $this->imitateIntersect($rids, $ridSets[$i]);
                }
                break;
            case self::SEARCH_KID:
                $kid = $this->keys[0]; //Assume keys is array of size one.
                if(Record::isKIDPattern($kid)) {
                    $validRecord = RecordController::getRecord($kid);
                    if(!is_null($validRecord) && $validRecord->form_id == $this->fid)
                        $rids[] =  $validRecord->id;
                }
                if(Record::isLegacyKIDPattern($kid)) {
                    $validRecord = RecordController::getRecordByLegacy($kid);
                    if(!is_null($validRecord) && $validRecord->form_id == $this->fid)
                        $rids[] =  $validRecord->id;
                }
                break;
            default:
                break;
        }

        return $rids;
    }

    private function imitateMerge(&$array1, &$array2) {
        foreach($array2 as $i) {
            $array1[] = $i;
        }
    }

    private function imitateIntersect($s1,$s2) {
        sort($s1);
        sort($s2);
        $i=0;
        $j=0;
        $N = count($s1);
        $M = count($s2);
        $intersection = array();

        while($i<$N && $j<$M) {
            if($s1[$i]<$s2[$j]) $i++;
            else if($s1[$i]>$s2[$j]) $j++;
            else {
                $intersection[] = $s1[$i];
                $i++;
                $j++;
            }
        }

        return $intersection;
    }

    /**
     * Prepares a statement for mysql search. Based on things we found in kora.
     *
     * @param  array $keys - Statements to prepare
     * @return array - The cleaned array
     */
    public static function prepare($keys) {
        $filtered = array();
        foreach($keys as $arg) {
            $arg = addslashes(trim($arg));
            $arg = str_replace('_', '\_', $arg);
            $arg = str_replace('%', '\%', $arg);

            $filtered[] = $arg;
        }

        return $filtered;
    }

    /**
     * Converts characters in a string to their close english only non-accented, non-diacritical matches.
     * The actual conversion is not super important, however consistency is, this is used to ensure a word like
     * "manana" matches what the search probably meant, "mañana".
     *
     * @param  string $string - String to convert
     * @return string - The converted string
     */
    public static function convertCloseChars($string) {
        return str_replace(self::$SPECIALS, self::$CLOSE_ASCII, $string);
    }

    /**
     * @var array - Special characters the user might enter
     */
    public static $SPECIALS = ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð',
        'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è',
        'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā',
        'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė',
        'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ',
        'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń',
        'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ',
        'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų',
        'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ',
        'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ',
        'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή'];

    /**
     * @var array - Their translations deemed "close" by a skilled observer
     */
    public static $CLOSE_ASCII = ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D',
        'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e',
        'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a',
        'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e',
        'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i',
        'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n',
        'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S',
        's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U',
        'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i',
        'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'a', 'Ε', 'e',
        'Ο', 'ο', 'O', 'w', 'Ι', 'i', 'i', 'i', 'Υ', 'u', 'u', 'u', 'Η', 'n'];
}
