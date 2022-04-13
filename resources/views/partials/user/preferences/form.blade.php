<div class="form-group mt-xxxl">
    <h2 class="sub-title">Use Dashboard?</h2>
    <p class="description">You can select to turn the dashboard off entirely.</p>

    <div class="check-box-half">
        <input type="checkbox" {{ $user->preferences['use_dashboard'] ? 'checked' : '' }} value="true" name="useDashboard" class="check-box-input check-box-input-js" />
        <span class="check"></span>
        <span class="placeholder">Use Dashboard</span>
    </div>
    <div class="check-box-half">
        <input type="checkbox" {{ !$user->preferences['use_dashboard'] ? 'checked' : '' }} value="false" name="useDashboard" class="check-box-input check-box-input-js" />
        <span class="check"></span>
        <span class="placeholder">Do Not Use Dashboard</span>
    </div>
</div>

<div class="form-group mt-xxxl">
    <div class="spacer"></div>
</div>

<div class="form-group mt-xxxl">
    <h2 class="sub-title">Kora Theme</h2>
    <p class="description">Select your preferred application theme.</p>
    @foreach ($themeOptions as $key => $name)
        <div class="check-box-half">
            <!-- I added this after initial creation, so we need to deal with the case that a user doesn't have this preference set -->
            <input type="checkbox" {{
                (isset($user->preferences['kora_theme']) && $user->preferences['kora_theme'] == $key) || (!isset($user->preferences['kora_theme']) && $key==1)
                ? 'checked' : ''
            }} value="{{ $key }}" name="logoTarget" class="check-box-input check-box-input-js" />
            <span class="check"></span>
            <span class="placeholder">{{ $name }}</span>
        </div>
    @endforeach
</div>

<div class="form-group mt-xxxl">
    <div class="spacer"></div>
</div>

<div class="form-group mt-xxxl">
    <h2 class="sub-title">Kora Home Target</h2>
    <p class="description">Upon login, or selecting the Kora logo in the top left corner, where would you like to be taken?</p>
    @foreach ($logoTargetOptions as $key => $name)
        <div class="check-box-half">
            <input type="checkbox" {{ $user->preferences['logo_target'] == $key ? 'checked' : '' }} value="{{ $key }}" name="logoTarget" class="check-box-input check-box-input-js" />
            <span class="check"></span>
            <span class="placeholder">{{ $name }}</span>
        </div>
    @endforeach
</div>

<div class="form-group mt-xxxl">
    <div class="spacer"></div>
</div>

<div class="form-group mt-xxxl">
    <h2 class="sub-title">Projects Page Tab Selection</h2>
    <p class="description">Select which tab you wish to be displayed when coming to the Projects page.</p>
    @foreach ($projPageTabSelOptions as $key => $name)
        <div class="check-box-half">
            <input type="checkbox" {{ $user->preferences['proj_tab_selection'] == $key ? 'checked' : '' }} value="{{ $key }}" name="projPageTabSel" class="check-box-input check-box-input-js" />
            <span class="check"></span>
            <span class="placeholder">{{ $name }}</span>
        </div>
    @endforeach
</div>

<div class="form-group mt-xxxl">
    <div class="spacer"></div>
</div>

<div class="form-group mt-xxxl">
    <h2 class="sub-title">Single Project Page Tab Selection</h2>
    <p class="description">Select which tab you wish to be displayed when coming to the a single project main page.</p>
    @foreach ($singleProjTabSelOptions as $key => $name)
        <div class="check-box-half">
            <input type="checkbox" {{ $user->preferences['form_tab_selection'] == $key ? 'checked' : '' }} value="{{ $key }}" name="formPageTabSel" class="check-box-input check-box-input-js" />
            <span class="check"></span>
            <span class="placeholder">{{ $name }}</span>
        </div>
    @endforeach
</div>

<div class="form-group mt-xxxl">
    <div class="spacer"></div>
</div>

<div class="form-group preferences-update-button mt-xxxl">
    {!! Form::submit('Update Preferences',['class' => 'btn edit-btn update-preferences-submit pre-fixed-js']) !!}
</div>

{!! Form::close() !!}

<div class="form-group my-xxxl">
    <h2 class="sub-title">Replay Kora Introduction?</h2>
    @if (!\App\Http\Controllers\Auth\UserController::returnUserPrefs('onboarding'))
        {!! Form::open(['method' => 'PATCH', 'url' => action('Auth\UserController@toggleOnboarding'), 'enctype' => 'multipart/form-data', 'class' => 'bottom-form-js']) !!}
        <p><button type="submit" class="text underline-middle-hover">Replay Kora Introduction</button></p>
        {!! Form::close() !!}
    @else
        <p class="bottom-form-js"><a href="{{ url('/') }}" class="text underline-middle-hover">Replay Kora Introduction</a></p>
    @endif
</div>