@extends('app', ['page_title' => "Editing {$project->name}", 'page_class' => 'project-edit'])

@section('leftNavLinks')
  @include('partials.menu.project', ['pid' => $project->id])
  @include('partials.menu.static', ['name' => 'Edit Project'])
@stop

@section('stylesheets')
  <!-- No Additional Stylesheets Necessary -->
@stop

@section('aside-content')
  @include('partials.sideMenu.project', ['pid' => $project->id, 'openDrawer' => true])
@stop

@section('header')
  <section class="head">
      <a class="back" href=""><i class="icon icon-chevron"></i></a>
      <div class="inner-wrap center">
        <h1 class="title">
          <i class="icon icon-project-edit"></i>
          <span>Edit Project</span>
        </h1>
        <p class="description">Edit the project information below, and then select “Update Project”</p>
      </div>
  </section>
@stop

@section('body')
  <section class="edit-form">
    {!! Form::model($project,  ['method' => 'PATCH', 'action' => ['ProjectController@update', $project->id], 'class' => 'edit-form center']) !!}
    @include('partials.projects.form',['projectMode' => $projectMode, 'pid' => $project->id, 'type' => 'edit'])
    {!! Form::close() !!}

    <div class="modal modal-js modal-mask project-cleanup-modal-js">
      <div class="content small">
        <div class="header">
          <span class="title title-js"></span>
          <a href="#" class="modal-toggle modal-toggle-js">
            <i class="icon icon-cancel"></i>
          </a>
        </div>
        <div class="body">
          @include("partials.projects.edit.projectArchiveForm")
          @include("partials.projects.edit.projectDeleteForm")
        </div>
      </div>
    </div>
  </section>
@stop

@section('footer')

@stop

@section('javascripts')
  @include('partials.projects.javascripts')

  <script type="text/javascript">
    var validationUrl = "{{ action('ProjectController@validateProjectFields', ["projects" => $project->id]) }}";
    var csrfToken = "{{ csrf_token() }}";

    Kora.Projects.Edit();
  </script>
@stop
