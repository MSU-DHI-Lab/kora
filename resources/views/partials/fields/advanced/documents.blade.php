<div class="form-group mt-xl">
    {!! Form::label('filesize','Max File Size (kb): ') !!}
    <input type="number" name="filesize" class="text-input" step="1" value="0" min="0">
</div>

<div class="form-group mt-xl">
    {!! Form::label('maxfiles','Max File Amount: ') !!}
    <input type="number" name="maxfiles" class="text-input" step="1" value="0" min="0">
</div>

<div class="form-group mt-xl">
    <label for="filetype">Allowed File Types (<a href="https://en.wikipedia.org/wiki/MIME">MIME</a>): </label>
    {!! Form::select('filetype'.'[]',\App\FileTypeField::getMimeTypesClean(), null, ['class' => 'multi-select', 'Multiple']) !!}
</div>

<script>
    Kora.Fields.Options('Documents');
</script>