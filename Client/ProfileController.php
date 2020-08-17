<?php

namespace App\Http\Controllers\Client;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProfileController extends Controller
{

    public function index()
    {
        return view('client.profile.index');
    }

    public function update(Request $request)
    {
        $request->except('email');

        $this->validate($request, [
            'name' => 'required',
        ]);

        auth('client')->user()->name = $request->get('name');
        auth('client')->user()->save();

        session()->flash('success', 'Информация сохранена');

        return redirect()->route('client.profile.index');
    }

    public function password(Request $request)
    {
        $this->validate($request, [
            'password' => 'required|confirmed',
        ]);

        auth('client')->user()->password = \Hash::make($request->get('password'));
        auth('client')->user()->save();

        session()->flash('success', 'Пароль изменен');

        return redirect()->route('client.profile.index');
    }

}
