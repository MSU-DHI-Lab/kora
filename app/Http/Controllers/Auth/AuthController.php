<?php namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Registrar;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller {

	/*
	|--------------------------------------------------------------------------
	| Registration & Login Controller
	|--------------------------------------------------------------------------
	|
	| This controller handles the registration of new users, as well as the
	| authentication of existing users. By default, this controller uses
	| a simple trait to add these behaviors. Why don't you explore it?
	|
	*/

	use AuthenticatesAndRegistersUsers;
	
	protected $redirectTo = '/projects';

	/**
	 * Create a new authentication controller instance.
	 */
	public function __construct()
    {
		$this->middleware('guest', ['except' => 'getLogout']);
	}

	/**
	 * Get a validator for an incoming registration request.
	 *
	 * @param  array  $data
	 * @return \Illuminate\Contracts\Validation\Validator
	 */
	public function validator(array $data)
	{
		return Validator::make($data, [
			'username' => 'required|max:255|unique:users', //Check to not contain 'a'
			'email' => 'required|email|max:255|unique:users',
			'password' => 'required|confirmed|min:6',
			'language'=> 'required|alpha|max:2',
		]);
	}

	/**
	 * Create a new user instance after a valid registration.
	 *
	 * @param  array  $data
	 * @return User
	 */
	public function create(array $data)
	{
		return User::create([
			'username' => $data['username'],
			'name' => $data['name'],
			'email' => $data['email'],
			'password' => bcrypt($data['password']),
			'organization' => $data['organization'],
			'regtoken' => $data['regtoken'],
			'language' => $data['language'],
		]);
	}
}
