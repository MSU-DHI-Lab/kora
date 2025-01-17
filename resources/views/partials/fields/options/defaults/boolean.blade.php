@php
    if(isset($seq)) { //Combo List
        $seq = '_' . $seq;
        $title = $cfName;
        $default = null;
    } else {
        $seq = '';
        $title = 'Default';
        $default = $field['default'];
    }
@endphp
<div class="form-group mt-xl">
    {!! Form::label('default' . $seq, $title) !!}
    <div class="check-box-half">
        <input type="checkbox" value="1" id="preset" class="check-box-input" name="default{{$seq}}"
                {{ ((!is_null($default) && $default) ? 'checked' : '') }}>
        <span class="check"></span>
        <span class="placeholder"></span>
    </div>
</div>
