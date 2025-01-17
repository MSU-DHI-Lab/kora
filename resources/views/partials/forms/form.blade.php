{!! Form::hidden('project_id',$pid) !!}

<div class="form-group">
  {!! Form::label('name', 'Form Name') !!}
    <span class="error-message">{{array_key_exists("name", $errors->messages()) ? $errors->messages()["name"][0] : ''}}</span>
  @if ($type == 'edit')
    {!! Form::text('name', null, ['class' => 'text-input' . (array_key_exists("name", $errors->messages()) ? ' error' : ''), 'placeholder' => 'Enter the form name here']) !!}
  @else
    {!! Form::text('name', null, ['class' => 'text-input' . (array_key_exists("name", $errors->messages()) ? ' error' : ''), 'placeholder' => 'Enter the form name here', 'autofocus']) !!}
  @endif
</div>

<div class="form-group mt-xl">
  {!! Form::label('description', 'Description') !!}
    <span class="error-message">{{array_key_exists("description", $errors->messages()) ? $errors->messages()["description"][0] : ''}}</span>
  {!! Form::textarea('description', null, ['class' => 'text-area' . (array_key_exists("description", $errors->messages()) ? ' error' : ''), 'placeholder' => "Enter the form's description here (max. 1000 characters)"]) !!}
</div>

@if($submitButtonText == 'Create Form')
  <div class="form-group mt-xl">
    {!! Form::label('admins', 'Select Additional Form Admins') !!}
    {!! Form::select('admins[]', $userNames, null, [
      'class' => 'multi-select',
      'multiple',
      'data-placeholder' => "Search and select the form admins",
      'id' => 'admins'
    ]) !!}
    <p class="sub-text mt-xs">
      Project admins are automatically assigned as admins to this new form, but you may select additional form admins above.  As the creator of this form, you are automatically added as a form admin as well.
    </p>
  </div>

  @if(count($presets) > 0)
    <div class="form-group mt-xxxl">
      <div class="check-box-half">
        <input type="checkbox" value="1" id="active" class="check-box-input preset-input-js" name="active" />
        <span class="check"></span>
        <span class="placeholder">Apply Form Preset?</span>
      </div>

      <p class="sub-text mt-sm">
        This will apply the form layout structure of the selected form preset to this newly created form.
      </p>
    </div>

    <div class="form-group preset-select-container preset-select-container-js">
      <div class="preset-select-js mt-xl">      
        {!! Form::label('preset', 'Select a Preset') !!}
        {!! Form::select('preset[]', [null=>null] + $presets, null, [
          'class' => 'single-select',
          'data-placeholder' => "Search and select the preset",
          'id' => 'presets'
        ]) !!}
      </div>
    </div>
  @endif

  <div class="form-group mt-xxxl mb-max">
    {!! Form::submit($submitButtonText, ['class' => 'btn validate-form-js']) !!}
  </div>
@else
  <div class="form-group mt-xl">
    <label for="preset">Use this Form as a Preset?</label>
    <div class="check-box">
      <input type="checkbox" value="1" id="preset" class="check-box-input" name="preset" {{$form->preset ? 'checked': ''}} />
      <div class="check-box-background"></div>
      <span class="check"></span>
      <span class="placeholder">Form is <strong>not</strong> set as a preset</span>
      <span class="placeholder-alt">Form is set as a preset</span>
    </div>

    <p class="sub-text mt-sm">
      Setting this form as a preset will  allow you to apply this forms information and layout structure to a new form.
    </p>
  </div>

  <div class="form-group">
    <div class="spacer"></div>

    <div class="form-permissions">
      <span class="question">Need to Edit Form Permissions?</span>

      <a class="action underline-middle-hover" href="{{action('FormGroupController@index', ['pid'=>$form->project_id,'fid'=>$form->id])}}">
        <span>Go to Form Permissions Page</span>
        <i class="icon icon-arrow-right"></i>
      </a>
    </div>
  </div>

@if (\Auth::user()->admin || \Auth::user()->isFormAdmin($form))
  <div class="form-group no-padding">
    <div class="spacer"></div>

    <div class="form-file-size">
      <p class="title">Current Form Filesize - {{$filesize}}</p>
      <div class="button-container">
        @if($filesize=="0 B")
          <a href="#" class="btn half-sub-btn warning delete-files-js disabled">Delete Old Record Files</a>
        @else
          <a href="#" class="btn half-sub-btn warning delete-files-js">Delete Old Record Files</a>
        @endif

        @if($form->getRecordCount()==0)
            <a href="#" class="btn half-sub-btn warning delete-records-js disabled">Delete All Form Records?</a>
        @else
            <a href="#" class="btn half-sub-btn warning delete-records-js">Delete All Form Records?</a>
        @endif
      </div>
    </div>

    <div class="spacer"></div>
  </div>
@endif

  <div class="form-group form-update-button">
    {!! Form::submit('Update Form',['class' => 'btn edit-btn update-form-submit pre-fixed-js validate-form-js']) !!}
  </div>

  <div class="form-group">
    <div class="form-cleanup">
      <a class="btn dot-btn trash warning form-trash-js tooltip" href="#" tooltip="Delete Form">
        <i class="icon icon-trash"></i>
      </a>
    </div>
  </div>
@endif
