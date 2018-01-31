<div class="modal modal-js modal-mask new-permission-modal new-permission-modal-js">
  <div class="content">
    <div class="header">
      <span class="title">Create a New Form Association</span>
      <a href="#" class="modal-toggle modal-toggle-js">
        <i class="icon icon-cancel"></i>
      </a>
    </div>
    <div class="body">
      {!! Form::open(['method' => 'POST', 'action' => ['AssociationController@create', $project->pid, $form->fid]]) !!}
        <div class="form-group">
          {!! Form::label("form", "Select a Form to Allow Association") !!}
          <select class="single-select" id="form" name="assocfid"
            data-placeholder="Select a form here">
            <option></option>
            @foreach ($available_associations as $association)
              <option value="{{$association->fid}}">{{$association->name}}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group mt-xxl add-association-submit add-association-submit-js">
          {!! Form::submit('Create a New Form Association', ['class' => 'btn']) !!}
        </div>
      {!! Form::close() !!}
    </div>
  </div>
</div>