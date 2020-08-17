<?php

namespace App\Http\Controllers\ClientAuth;

use App\Models\Statistic;
use App\Models\TrafficFlow;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Validator;
use App\Models\Client;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest:client');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:clients',
            'password' => 'required|min:4|confirmed',
            'agreement' => 'required'
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     * @return Client
     */
    protected function create(array $data, $traffic_flow)
    {
        $client = new Client();
        $client->partner_id = $traffic_flow->partner_id;
        $client->name = $data['name'];
        $client->email = $data['email'];
        $client->password = \Hash::make($data['password']);
        $client->bonus_balance = $traffic_flow->bonus;
        $client->save();

        Statistic::write($client, 'registrations');

        return $client;
    }

    public function register_complete(Request $request, $code = null)
    {
        $traffic_flow = TrafficFlow::where('code', $code)->first();

        $validator = $this->validator($request->all());

        $validator->after(function ($validator) use ($traffic_flow) {
            if (!$traffic_flow) {
                $validator->errors()->add('code', 'Бонусный код не существует.');
            } else {
                $traffic_flow->last_visit_at = (new Carbon());
                $traffic_flow->save();
            }
        });

        $validator->validate();

        $data = $request->all();

        \Session::flash('flash_message', 'Поздравляем Вас с успешной регистрацией! Пополняйте свой игровой баланс и делайте ставки!');
        event(new Registered($user = $this->create($data, $traffic_flow)));

        $this->guard('client')->login($user);

//        session()->flash('success', 'Поздравляем Вас с успешной регистрацией. Пополняйте свой игровой баланс и делайте ставки!');
//        session()->flash('flash_message', 'Your article has been created!');
//        session()->flash('flash_message_important', true);
        return $this->registered($request, $user) ?: redirect($this->redirectPath());
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showRegistrationForm($code = null)
    {
        $traffic_flow = TrafficFlow::where('code', $code)->first();
        if (!$traffic_flow) {
            $traffic_flow = new TrafficFlow();
        }

        return view('auth.client.register', ['traffic_flow' => $traffic_flow]);
    }

    /**
     * Get the guard to be used during registration.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('client');
    }
}
