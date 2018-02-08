<div class="form-group mt-xl">
    {!! Form::label('filesize','Max File Size (kb): ') !!}
    <input type="number" name="filesize" class="text-input" step="1" value="0" min="0">
</div>

<div class="form-group mt-xl">
    {!! Form::label('filetype','Allowed File Types (MIME): ') !!}
    {!! Form::select('filetype'.'[]',['obj' => 'OBJ','stl' => 'STL'], null, ['class' => 'multi-select', 'Multiple']) !!}
</div>

<div class="form-group mt-xl">
    {!! Form::label('color','Model Color: ') !!}
    <input type="color" name="color" class="text-input color-input" value="#CAA618">
</div>

<div class="form-group mt-xl">
    {!! Form::label('backone','Background Color One: ') !!}
    <input type="color" name="backone" class="text-input color-input" value="#ffffff">
</div>

<div class="form-group mt-xl">
    {!! Form::label('backtwo','Background Color Two: ') !!}
    <input type="color" name="backtwo" class="text-input color-input" value="#383840">
</div>

<script>
    Kora.Fields.Options('Model');
</script>