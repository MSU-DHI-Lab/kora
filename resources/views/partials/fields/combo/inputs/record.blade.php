@if($type=='Text')
    <div class="form-group
        @if($fnum=='two')
            mt-xxxl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName) !!}
        {!! Form::text('default_'.$fnum, null, ['id' => 'default_'.$fnum.'_'.$flid, 'class' => 'text-input', 'placeholder' => 'Enter text value here']) !!}
    </div>
@elseif($type=='Integer' | $type=='Float')
    <div class="form-group
        @if($fnum=='two')
            mt-xxxl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName.' ('.App\KoraFields\ComboListField::getComboFieldOption($field, "Unit", $fnum).')') !!}
        {!! Form::number('default_'.$fnum, null, ['id' => 'default_'.$fnum.'_'.$flid, 'class' => 'text-input', 'placeholder' => 'Enter number here', 'min' => App\KoraFields\ComboListField::getComboFieldOption($field, "Min", $fnum), 'max' => App\KoraFields\ComboListField::getComboFieldOption($field, "Max", $fnum)]) !!}
    </div>
@elseif($type=='Date')
    <div class="form-group
        @if($fnum=='two')
            mt-xxxl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName.': ') !!}
        <div class="form-group mt-sm">
            {!! Form::label('default_month_'.$fnum,'Month: ') !!}
            {!! Form::select('default_month_'.$fnum,['' => '',
                '1' => '01 - '.date("F", mktime(0, 0, 0, 1, 10)), '2' => '02 - '.date("F", mktime(0, 0, 0, 2, 10)),
                '3' => '03 - '.date("F", mktime(0, 0, 0, 3, 10)), '4' => '04 - '.date("F", mktime(0, 0, 0, 4, 10)),
                '5' => '05 - '.date("F", mktime(0, 0, 0, 5, 10)), '6' => '06 - '.date("F", mktime(0, 0, 0, 6, 10)),
                '7' => '07 - '.date("F", mktime(0, 0, 0, 7, 10)), '8' => '08 - '.date("F", mktime(0, 0, 0, 8, 10)),
                '9' => '09 - '.date("F", mktime(0, 0, 0, 9, 10)), '10' => '10 - '.date("F", mktime(0, 0, 0, 10, 10)),
                '11' => '11 - '.date("F", mktime(0, 0, 0, 11, 10)), '12' => '12 - '.date("F", mktime(0, 0, 0, 12, 10))],
                null, ['id' => 'default_month_'.$fnum.'_'.$flid, 'class' => 'single-select', 'data-placeholder'=>"Select a Month"]) !!}
        </div>
        <div class="form-group mt-sm">
            {!! Form::label('default_day_'.$fnum,'Day: ') !!}
            <select id="default_day_{{$fnum}}_{{$flid}}" name="default_day_{{$fnum}}" class="single-select" data-placeholder="Select a Day">
                <option value=""></option>
                <?php
                $i = 1;
                while ($i <= 31) {
                    echo "<option value=" . $i . ">" . $i . "</option>";
                    $i++;
                }
                ?>
            </select>
        </div>
        <div class="form-group mt-sm">
            {!! Form::label('default_year_'.$fnum,'Year: ') !!}
            <select id="default_year_{{$fnum}}_{{$flid}}" name="default_year_{{$fnum}}" class="single-select preset-clear-chosen-js" data-placeholder="Select a Year">
                <option value=""></option>
                <?php
                $currYear=0;
                $i = App\KoraFields\ComboListField::getComboFieldOption($field, "Start", $fnum);
                $j = App\KoraFields\ComboListField::getComboFieldOption($field, "End", $fnum);
                while ($i <= $j) {
                    echo "<option value=" . $i . ">" . $i . "</option>";
                    $i++;
                }
                ?>
            </select>
        </div>
    </div>
@elseif($type=='List')
    <div class="form-group
        @if($fnum=='two')
            mt-xxxl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName) !!}
        {!! Form::select('default_'.$fnum,App\KoraFields\ComboListField::getComboList($field,false,$fnum), null,
            ['id' => 'default_'.$fnum.'_'.$flid, 'class' => 'single-select']) !!}
    </div>
@elseif($type=='Multi-Select List')
    <div class="form-group
        @if($fnum=='two')
            mt-xl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName) !!}
        {!! Form::select('default_'.$fnum.'[]',App\KoraFields\ComboListField::getComboList($field,false,$fnum), null,
        ['id' => 'default_'.$fnum.'_'.$flid, 'class' => 'multi-select', 'multiple']) !!}
    </div>
@elseif($type=='Generated List')
    <div class="form-group
        @if($fnum=='two')
            mt-xxxl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName) !!}
        {!! Form::select('default_'.$fnum.'[]',App\KoraFields\ComboListField::getComboList($field,false,$fnum), null,
        ['id' => 'default_'.$fnum.'_'.$flid, 'class' => 'multi-select modify-select', 'multiple']) !!}
    </div>
@elseif($type=='Associator')
    <div class="form-group
        @if($fnum=='two')
            mt-xxxl
        @else
            mt-xl
        @endif
            ">
        {!! Form::label('default_'.$fnum, $cfName) !!}
        {!! Form::select('default_'.$fnum.'[]', [], null, ['id' => 'default_'.$fnum.'_'.$flid, 'class' => 'multi-select assoc-default-records-js',
            'multiple', "data-placeholder" => "Search below to add associated records"]) !!}
    </div>

{{--     <div class="form-group mt-xs">
        {!! Form::label('search','Search Associations') !!}
        <input type="text" class="text-input assoc-search-records-js" placeholder="Enter search term or KID to find associated records (populated below)"
           search-url="{{ action('AssociatorSearchController@assocSearch',['pid' => $field->pid,'fid'=>$field->fid, 'flid'=>$field->flid]) }}">
    </div> --}}

    <div class="form-group mt-xs">
        {!! Form::label('search','Association Results') !!}
        {!! Form::select('search[]', [], null, ['class' => 'multi-select assoc-select-records-js', 'multiple',
            "data-placeholder" => "Select a record association to add to defaults"]) !!}
    </div>
@endif
