<?php

namespace App\Http\Controllers\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GameBalanceController extends Controller
{
    public function store(Request $request){

        $this->validate($request, [
            'amount' => 'required',
            'payment_type' => 'required',
        ]);

        $client = auth('client')->user();

        if($client->balance < $request->amount){
            session()->flash('error', 'На Вашем балансе недостаточно средств!');
            return back()->withInput();
        }
        $payment = new Payment([
            'type' => $request->payment_type,
            'client_id' => $client->id,
            'partner_id' => $client->partner_id,
            'amount' => $request->amount,
            'payment_status' => 'completed',
            'gateway' => 'game_balance',
            ]);
        $payment->save();
        $client->balance = $client->balance - $request->amount;
        $client->step = $request->payment_type;
        $client->save();
        session()->flash('success', 'Платеж совершен успешно!');
        return redirect()->route('client.withdraw.index');

    }
}
