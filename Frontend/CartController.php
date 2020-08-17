<?php

namespace App\Http\Controllers\Frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CartController extends Controller
{

    public function load(Request $request)
    {
        $client = auth('client')->user();
        if($client){
            $request->session()->put('client_bets_count',count($client->bets));
            $request->session()->put('client_step',$client->step);
            $request->session()->put('client_status',$client->status);
            $request->session()->put('client_balance',$client->balance);
        }
//        session()->put('cart', [
//            md5('Санднес - Строммен|1x2|П1|1.77') => ['id', 'group', 1],
//        ]);
//
//        dd(session('cart'));

        return view('frontend.cart.load');
    }

    public function toggle(Request $request)
    {
        $hash = md5(implode('|', [
            'id' => $request->get('id'),
            'group' => trim($request->get('group')),
            'title' => trim($request->get('title')),
            'value' => trim($request->get('value')),
        ]));

        if (session()->has('cart.' . $hash)) {
            session()->remove('cart.' . $hash);
        } else {
            session()->put('cart.' . $hash, array_merge($request->all(), ['hash' => $hash]));
        }

        return view('frontend.cart.load');
    }

    public function remove($hash)
    {
        if (session()->has('cart.' . $hash)) {
            session()->remove('cart.' . $hash);
        }

        return view('frontend.cart.load');
    }

}
