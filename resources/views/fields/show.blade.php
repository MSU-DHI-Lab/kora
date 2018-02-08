@extends('app', ['page_title' => $field->name, 'page_class' => 'field-show'])

@section('leftNavLinks')
    @include('partials.menu.project', ['pid' => $field->pid])
    @include('partials.menu.form', ['pid' => $field->pid, 'fid' => $field->fid])
    @include('partials.menu.static', ['name' => $field->name])
@stop

@section('header')
    <section class="head">
        <div class="inner-wrap center">
            <h1 class="title">
                <i class="icon icon-field"></i>
                <span>{{$field->name}}</span>
            </h1>
            <p class="description"><b>Field Type</b>: {{$field->type}}</p>
        </div>
    </section>
@stop

@section('body')
    @include("partials.fields.input-modals")

    <section class="single-field center">
        {!! Form::model($field,  ['method' => 'PATCH', 'action' => ['FieldController@update', $field->pid, $field->fid, $field->flid]]) !!}
        @include('partials.fields.options', ['field'=>$field])
        {!! Form::close() !!}

        {{--TODO::@include('partials.option_preset')--}}

        @include('partials.fields.modals.fieldCleanupModal', ['field'=>$field])
    </section>
@stop

@section('footer')

@stop

@section('javascripts')
    @include('partials.fields.javascripts')

    <script type="text/javascript">
        Kora.Fields.Show();

        @yield('fieldOptionsJS')
    </script>
@stop