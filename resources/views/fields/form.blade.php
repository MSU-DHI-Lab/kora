{!! Form::hidden('pid',$pid) !!}
{!! Form::hidden('fid',$fid) !!}
<div class="form-group">
    {!! Form::label('name',trans('fields_form.name').': ') !!}
    {!! Form::text('name',null,['class' => 'form-control']) !!}
</div>

<div class="form-group">
    {!! Form::label('slug',trans('fields_form.slug').': ') !!}
    {!! Form::text('slug',null,['class' => 'form-control']) !!}
</div>

<div class="form-group" id="field_types_div">
    {!! Form::label('type',trans('fields_form.type').': ') !!}
    {!! Form::select('type',
        [trans('fields_form.tf') => array('Text' => trans('fields_form.text'), 'Rich Text' => trans('fields_form.richtext'),
            'Number' => trans('fields_form.number')),
        trans('fields_form.lf') => array('List' => trans('fields_form.list'), 'Multi-Select List' => trans('fields_form.mslist'),
            'Generated List' => trans('fields_form.genlist'), 'Combo List' => trans('fields_form.combolist')),
        trans('fields_form.df') => array('Date' => trans('fields_form.date'), 'Schedule' => trans('fields_form.schedule')),
        trans('fields_form.ff') => array('Documents' => trans('fields_form.doc'),'Gallery' => trans('fields_form.gallery').' (jpg, gif, png)',
            'Playlist' => trans('fields_form.playlist').' (mp3, wav, oga)', 'Video' => trans('fields_form.video').' (mp4, ogv)',
            '3D-Model' => trans('fields_form.model').' (obj, stl)'),
        trans('fields_form.sf') => array('Geolocator' => trans('fields_form.geo').' (latlon, utm, textual)','Associator' => trans('fields_form.assoc'))],
        null,['class' => 'form-control field_types']) !!}
    <div id="combo_field_types" style="display: none">
        {!! Form::label('cftype1',trans('fields_form.combotype').' 1: ') !!}
        {!! Form::select('cftype1',
            [trans('fields_form.tf') => array('Text' => trans('fields_form.text'), 'Number' => trans('fields_form.number')),
            trans('fields_form.lf') => array('List' => trans('fields_form.list'), 'Multi-Select List' => trans('fields_form.mslist'),
            'Generated List' => trans('fields_form.genlist'))],
            null,['class' => 'form-control']) !!}
        {!! Form::label('cfname1',trans('fields_form.comboname').' 1: ') !!}
        {!! Form::text('cfname1',null,['class' => 'form-control']) !!}

        {!! Form::label('cftype2',trans('fields_form.combotype').' 2: ') !!}
        {!! Form::select('cftype2',
            [trans('fields_form.tf') => array('Text' => trans('fields_form.text'), 'Number' => trans('fields_form.number')),
            trans('fields_form.lf') => array('List' => trans('fields_form.list'), 'Multi-Select List' => trans('fields_form.mslist'),
            'Generated List' => trans('fields_form.genlist'))],
            null,['class' => 'form-control']) !!}
        {!! Form::label('cfname2',trans('fields_form.comboname').' 2: ') !!}
        {!! Form::text('cfname2',null,['class' => 'form-control']) !!}
    </div>

    <script>
        $( document ).ready(function() {
            if($('.field_types').val()=='Combo List'){
                $('#combo_field_types').show();
            }
        });
        $('#field_types_div').on('change','.field_types', function(){
            if($('.field_types').val()=='Combo List'){
                $('#combo_field_types').show();
            }else{
                $('#combo_field_types').hide();
            }
        });
    </script>
</div>

<div class="form-group">
    {!! Form::label('desc',trans('fields_form.desc').': ') !!}
    {!! Form::textarea('desc',null,['class' => 'form-control']) !!}
</div>

<div class="form-group">
    {!! Form::label('required',trans('fields_form.req').': ') !!}
    {!! Form::select('required',['false', 'true'], 'false', ['class' => 'form-control']) !!}
</div>

<div id="advance_options_div">
    <div class="form-group">
        <button type="button" id="adv_opt" class="btn form-control">Advanced Options</button>
    </div>
</div>

<div class="form-group">
    {!! Form::submit($submitButtonText,['class' => 'btn btn-primary form-control']) !!}
</div>

<script>
    var adv = false;

    $('#advance_options_div').on('click','#adv_opt',function(){
        //opens advanced options page for selected type
        $.ajax({
            url: "{{ action('OptionController@getAdvancedOptionsPage',['pid' => $pid,'fid'=>$fid]) }}",
            type: 'GET',
            data: {
                "_token": "{{ csrf_token() }}",
                type: $(".field_types").val()
            },
            success: function (result) {
                $('#advance_options_div').html(result);
            }
        });

        //set adv to true
        adv = true;
    });

    $('#field_types_div').on('focus', '.field_types', function () {
        // Store the current value on focus and on change
        previous = $(this).val();
    }).on('change','.field_types',function(){
        //if adv is true
        if(adv) {
            //dialog warning
            if (!confirm("Changing the field type will reset your advanced options. Are you sure you want to proceed?")) {
                $('.field_types').val(previous);
                return false;
            }
            //close advanced options page
            button = '<div class="form-group">';
            button += '<button type="button" id="adv_opt" class="btn form-control">Advanced Options</button>';
            button += '<div>';
            $('#advance_options_div').html(button);
            //set adv to false
            adv = false;
        }
    });

</script>