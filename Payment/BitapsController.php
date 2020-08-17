<?php

namespace App\Http\Controllers\Payment;

use Carbon\Carbon;
use App\Models\Refill;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BitapsController extends Controller
{

    public function status(Request $request)
    {
        $payment = Refill::findOrFail($request->get('payment_id'));

        if ($request->get('confirmations', 0) >= 1) {
            $amount = ($request->get('amount') + $request->get('payout_miner_fee') + $request->get('payout_service_fee')) / 100000000;
            $diff = ($payment->btc - $amount) > ($payment->btc * 0.15);
            $payment->payment_status = $diff ? 'invalid' : 'payed';
            $payment->hash = $request->get('tx_hash');
            $payment->confirmed_at = (new Carbon());

            if ($payment->payment_status == 'payed') {
                $client = $payment->client;
                if ($payment->btc >= $client->getRank('minimal_income')) {
                    $client->is_minimal_income = true;
                }
                if (!$client->license_type) {
                    $client->license_type = 'trial';
                    $client->last_rate = setting('middle_rate');
                }
                $client->balance_btc += $amount;
                $client->save();

                if ($client->license_type == 'trial') {
                    $client->available_trades = 9;
                    $client->save();
                }
            }

            $payment->blockchain_btc = $request->get('amount') / 100000000;
            $payment->setDataByName('amount', $request->get('amount'));
            $payment->setDataByName('payout_service_fee', $request->get('payout_service_fee'));
            $payment->setDataByName('payout_miner_fee', $request->get('payout_miner_fee'));
            $payment->setDataByName('payout_tx_hash', $request->get('payout_tx_hash'));
            $payment->setDataByName('code', $request->get('code'));
        }

        $payment->confirmations = $request->get('confirmations', 0);
        $payment->save();

        echo $payment->invoice_id;
        exit;
    }

}
