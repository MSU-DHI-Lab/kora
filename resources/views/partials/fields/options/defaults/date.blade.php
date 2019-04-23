@php
    if(isset($seq))
        $seq = '_' . $seq;
    else
        $seq = '';
@endphp
{{-- TODO::@andrew.joye might do mt-xxl instead for combo list --}}
{{-- {{ $seq ? 'mt-xxl' : 'mt-xl' }} --}}
<div class="form-group mt-xl">
    {!! Form::label('format' . $seq,'Date Format') !!}
    {!! Form::select('format' . $seq, ['MMDDYYYY' => 'MM DD, YYYY','DDMMYYYY' => 'DD MM YYYY','YYYYMMDD' => 'YYYY MM DD'], $field['options']['Format'], ['class' => 'single-select']) !!}
</div>

<div class="form-group mt-xl half pr-m">
    {!! Form::label('start' . $seq,'Start Year') !!}
    <span class="error-message"></span>
    <div class="number-input-container number-input-container-js">
        {!! Form::input('number', 'start' . $seq, $field['options']['Start'], ['class' => 'text-input start-year-js', 'placeholder' => 'Enter start year here']) !!}
    </div>
</div>

<div class="form-group mt-xl half pl-m">
    {!! Form::label('end' . $seq,'End Year') !!}
    <span class="error-message"></span>
    <div class="number-input-container number-input-container-js">
        {!! Form::input('number', 'end' . $seq, $field['options']['End'], ['class' => 'text-input end-year-js', 'placeholder' => 'Enter end year here']) !!}
    </div>
</div>