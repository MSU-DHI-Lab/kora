<?php

namespace App\Http\Controllers;

use App\Commands\SaveKora2Scheme;
use App\Form;
use App\FormGroup;
use App\OptionPreset;
use App\Project;
use App\ProjectGroup;
use App\Token;
use App\User;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ExodusController extends Controller{

    /**
     * User must be logged in and admin to access views in this controller.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('active');
        $this->middleware('admin');
        if(Auth::check()){
            if(Auth::user()->id != 1){
                flash()->overlay(trans('controller_backup.admin'),trans('controller_backup.whoops'));
                return redirect("/projects")->send();
            }
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('exodus.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function migrate(Request $request)
    {
        $users_exempt_from_lockout = new Collection();
        $users_exempt_from_lockout->put(1,1); //Add another one of these with (userid,userid) to exempt extra users
        $this->lockUsers($users_exempt_from_lockout);

        //MySQL Info
        $host = $request->host;
        $name = $request->name;
        $user = $request->user;
        $pass = $request->pass;

        $con = mysqli_connect($host,$user,$pass,$name);
        $userArray = array();
        $projectArray = array();
        $formArray = array();
        $pairArray = array();
        $permArray = array();
        $tokenArray = array();

        //we should do the user table and project related tables and then divide all the scheme tasks into queued jobs

        //Users
        $users = $con->query("select * from user where username!='koraadmin'");
        while ($u = $users->fetch_assoc()) {
            if($u['salt']!=0) {
                $email = $u['email'];
                if (!$this->emailExists($email)) {
                    $username = explode('@', $email)[0];
                    $i = 1;
                    $username_array = array();
                    $username_array[0] = $username;

                    // Increment a count while the username exists.
                    while ($this->usernameExists($username)) {
                        $username_array[1] = $i;
                        $username = implode($username_array);
                        $i++;
                    }

                    //
                    // Create the new user.
                    //
                    $user = new User();
                    $user->username = $username;
                    $user->email = $email;
                    $user->name = $u['realName'];
                    $user->admin = $u['admin'];
                    $user->organization = $u['organization'];
                    $password = $this->passwordGen();
                    $user->password = bcrypt($password);
                    $token = AuthenticatesAndRegistersUsers::makeRegToken();
                    $user->regtoken = $token;
                    $user->save();

                    //
                    // Send a confirmation email.
                    //
                    /*Mail::send('emails.batch-activation', compact('token', 'password', 'username'), function ($message) use ($email) {
                        $message->from(env('MAIL_FROM_ADDRESS'));
                        $message->to($email);
                        $message->subject('Kora Account Activation');
                    });*/

                    //add user to conversion array with new id
                    $userArray[$u['uid']] = $user->id;
                } else {
                    //add user to conversion using existing id so it's still relevant
                    $user = User::where('email', '=', $email)->first();
                    $userArray[$u['uid']] = $user->id;
                }
            }else{
                //salt is zero so we have a token and not a user
                $tid = $u['uid'];
                $token = $u['username'];
                $projects = array();
                $pids = $con->query("select * from member where uid=".$tid);
                while ($pid = $pids->fetch_assoc()) {
                    array_push($projects,$pid['pid']);
                }
                //save for later because we need to build new projects first
                $tokenArray[$token] = $projects;
            }
        }

        //Projects
        $projects = $con->query("select * from project");
        while ($p = $projects->fetch_assoc()) {
            //make project
            $proj = new Project();
            $proj->name = $p['name'];
            $slug = str_replace(' ','_',$p['name']);
            if (Project::where('slug', '=', $slug)->exists()) {
                $unique = false;
                $i=1;
                while(!$unique){
                    if(Project::where('slug', '=', $slug.$i)->exists()){
                        $i++;
                    }else{
                        $proj->slug = $slug.$i;
                        $unique = true;
                    }
                }
            }else{
                $proj->slug = $slug;
            }
            $proj->description = $p['description'];
            $proj->active = $p['active'];
            $proj->save();

            //add to project conversion array
            $projectArray[$p['pid']] = $proj->pid;

            //create permission groups
            $permGroups = $con->query("select * from permGroup where pid=".$p['pid']);
            while ($pg = $permGroups->fetch_assoc()) {
                $admin = false;
                $k3Group = new ProjectGroup();
                if($pg['name'] == 'Administrators'){
                    $k3Group->name = $proj->name.' Admin Group';
                    $admin = true;
                } else if($pg['name'] == 'Default'){
                    $k3Group->name = $proj->name.' Default Group';
                } else{
                    $k3Group->name = $pg['name'];
                }
                $k3Group->pid = $proj->pid;
                $k3Group->save();

                //this group is the admin group so save that info to the project
                if($admin){
                    $proj->adminGID=$k3Group->id;
                    $proj->save();
                }

                //add all the members to their appropriate groups
                $groupUsers = array();
                $members = $con->query("select * from member where gid=".$pg['gid']);
                while ($m = $members->fetch_assoc()) {
                    $gu = $userArray[$m['uid']];
                    array_push($groupUsers,$gu);
                }
                $k3Group->users()->attach($groupUsers);

                //this part is going to be interesting. especially at the form level
                $perms = $this->k2tok3Perms($pg['permissions']);
                //lets pair these permissions with their group id so we can reference it when we make the form groups
                $permArray[$k3Group->id] = $perms;
                $k3Group->create = $perms['pCreate'];
                $k3Group->edit = $perms['pEdit'];
                $k3Group->delete = $perms['pDelete'];
                $k3Group->save();
            }
        }

        //Back to tokens
        foreach($tokenArray as $t=>$tokenProjs){
            $token = new Token();
            $token->token = $t;
            $token->type = 'search';
            $token->save();

            //add all it's projects
            foreach($tokenProjs as $tpid){
                $newPid = $projectArray[$tpid];
                DB::table('project_token')->insert(
                    ['project_id' => $newPid, 'token_id' => $token->id]
                );
            }
        }

        //Option Presets
        $optPresets = $con->query("select * from controlPreset");
        while ($o = $optPresets->fetch_assoc()) {
            if($o['project']==0 | !isset($projectArray[$o['project']])){
                $optionPID = 0;
            }else{
                $optionPID = $projectArray[$o['project']];
            }
            switch($o['class']) {
                case 'TextControl':
                    if($o['value']!=''){
                        $preset = OptionPreset::create(['pid' => $optionPID, 'type' => 'Text', 'name' => $o['name'], 'preset' => $o['value']]);
                        $preset->save();
                        if ($o['global']) {
                            $preset->shared = 1;
                        } else {
                            $preset->shared = 0;
                        }
                        $preset->save();
                    }
                    break;
                case 'ListControl':
                    $xml = simplexml_load_string(utf8_encode($o['value']));
                    $options = array();
                    if(!is_null($xml->option)) {
                        foreach ((array)$xml->option as $opt) {
                            if($opt!=''){array_push($options,$opt);}
                        }
                    }
                    if(sizeof($options)>0){
                        $optString = implode('[!]',$options);
                        $preset = OptionPreset::create(['pid' => $optionPID, 'type' => 'List', 'name' => $o['name'], 'preset' => $optString]);
                        $preset->save();
                        if ($o['global']) {
                            $preset->shared = 1;
                        } else {
                            $preset->shared = 0;
                        }
                        $preset->save();
                    }
                    break;
            }
        }

        //Forms
        $forms = $con->query("select * from scheme");
        while ($f = $forms->fetch_assoc()) {
            //make form
            $form = new Form();
            $form->pid = $projectArray[$f['pid']];
            $form->name = $f['schemeName'];
            $slug = str_replace(' ','_',$f['schemeName']);
            if (Form::where('slug', '=', $slug)->exists()) {
                $unique = false;
                $i=1;
                while(!$unique){
                    if(Form::where('slug', '=', $slug.$i)->exists()){
                        $i++;
                    }else{
                        $form->slug = $slug.$i;
                        $unique = true;
                    }
                }
            }else{
                $form->slug = $slug;
            }
            $form->description = $f['description'];
            $form->layout = '<LAYOUT></LAYOUT>';
            $form->preset = $f['allowPreset'];;
            $form->public_metadata = 0;
            $form->save();

            //add to form conversion array
            $formArray[$f['schemeid']] = $form->fid;
            //add to old sid/pid array
            $pairArray[$f['schemeid']] = $f['pid'];

            //create admin/default groups based on project groups
            $permGroups = $con->query("select * from permGroup where pid=".$f['pid']);
            while ($pg = $permGroups->fetch_assoc()) {
                $admin = false;
                $k3Group = new FormGroup();
                if($pg['name'] == 'Administrators'){
                    $k3Group->name = $form->name.' Admin Group';
                    $nameOfProjectGroup = ProjectController::getProject($form->pid)->name.' Admin Group';
                    $admin = true;
                } else if($pg['name'] == 'Default'){
                    $k3Group->name = $form->name.' Default Group';
                    $nameOfProjectGroup = ProjectController::getProject($form->pid)->name.' Default Group';
                } else{
                    $k3Group->name = $pg['name'];
                    $nameOfProjectGroup = $pg['name'];
                }
                $k3Group->fid = $form->fid;
                $k3Group->save();

                //this group is the admin group so save that info to the project
                if($admin){
                    $form->adminGID=$k3Group->id;
                    $form->save();
                }

                //add all the members from the newly created project group to the respective form group
                $groupUsers = array();
                $projGroup = ProjectGroup::where('name','=',$nameOfProjectGroup)->where('pid','=',$form->pid)->first();
                foreach($projGroup->users()->get() as $user){
                    array_push($groupUsers,$user->id);
                }
                $k3Group->users()->attach($groupUsers);

                //get the perms from earlier
                $perms = $permArray[$projGroup->id];
                //lets pair these permissions with their group id so we can reference it when we make the form groups
                $k3Group->create = $perms['fCreate'];
                $k3Group->edit = $perms['fEdit'];
                $k3Group->delete = $perms['fDelete'];
                $k3Group->ingest = $perms['ingest'];
                $k3Group->modify = $perms['modify'];
                $k3Group->destroy = $perms['destroy'];
                $k3Group->save();
            }
        }

        mysqli_close($con);
        $dbInfo = array();
        $dbInfo['host'] = $host;
        $dbInfo['user'] = $user;
        $dbInfo['name'] = $name;
        $dbInfo['pass'] = $pass;

        //ini_set('max_execution_time',0);
        //Log::info("Begin Exodus");
        foreach($formArray as $sid=>$fid){
            //$job = new SaveKora2Scheme($sid,$fid,$pairArray, $dbInfo);
            //$this->dispatch($job->onQueue('exodus'));

        }

        /*Artisan::call('queue:listen', [
            '--queue' => 'exodus',
            '--timeout' => 1800
        ]);*/

        $this->unlockUsers(); //TODO: Move this eventually
    }

    //test function to test commands for queuing
    private function saveKora2Test($sid,$fid,$pairArray, $dbInfo){
        //$colls = $con->query("select * from collection where schemeid=".$sid);
    }

    /**
     * Checks if a username is in use.
     *
     * @param $username
     * @return bool
     */
    private function usernameExists($username)
    {
        return !is_null(User::where('username', '=', $username)->first());
    }

    /**
     * Checks if an email is in use.
     *
     * @param $email
     * @return bool
     */
    private function emailExists($email)
    {
        return !is_null(User::where('email', '=', $email)->first());
    }

    /**
     * Generates a random temporary password.
     *
     * @return string
     */
    private function passwordGen()
    {
        $valid = 'abcdefghijklmnopqrstuvwxyz';
        $valid .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $valid .= '0123456789';

        $password = '';
        for ($i = 0; $i < 10; $i++){
            $password .= $valid[( rand() % 62 )];
        }
        return $password;
    }

    private function k2tok3Perms($perm){
        $result = array();

        $result['pCreate'] = ((int)$perm & 1);
        $result['pEdit'] = ((int)$perm & 1);
        $result['pDelete'] = ((int)$perm & 1);

        $result['fCreate'] = ((int)$perm & 16);
        $result['fEdit'] = ((int)$perm & 16);
        $result['fDelete'] = ((int)$perm & 32);

        $result['ingest'] = ((int)$perm & 2);
        $result['modify'] = ((int)$perm & 2);
        $result['destroy'] = ((int)$perm & 4);

        return $result;
    }

    /*
     * This method takes a collection of user IDs as keys, and their username as value
     * It will lock any user that is not exempted, so that they cannot access the app during
     * backup and restore operations.  They should be unlocked afterwards.
     *
     * The default is [1,1]
     *
     * @params Collection $exemptions
     * @return
     */
    public function lockUsers(Collection $exemptions){
        $users = User::all();
        foreach($users as $user){
            if($exemptions->has($user->id)){
                continue;
            }
            else{
                $user->locked_out = true;
                $user->save();
            }
        }
    }
    /*
     * This method will unlock all users, it returns a response with a message and status code,
     * but the response isn't sent (unless this is called from a route).
     *
     * @params
     * @return response
     */
    public function unlockUsers(){

        try {
            $users = User::all();
            foreach ($users as $user) {
                $user->locked_out = false;
                $user->save();
            }
        }
        catch(\Exception $e){
            return response("error",500);
        }
        return response("success",200);
    }
}
