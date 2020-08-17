<?php

namespace App\Http\Controllers\Payment;

use App\Models\Payment;
use App\Models\Refill;
use App\Models\Statistic;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PiastrixController extends Controller
{

    public function status(Request $request)
    {
        $uspt = $request->get('us_payment_type');

        if ($uspt == 'refill') {
            $refill = Refill::findOrFail($request->get('shop_order_id'));
            $refill->payment_data = $request->all();
            $refill->save();

            if ($refill->payment_status != 'payed' && setting('piastrix_shop_id') == $request->get('shop_id') && $request->get('status') == 'success') {
                $refill->payment_status = 'payed';
                $refill->invoice_id = $request->get('payment_id');
                $refill->payed_at = new \Carbon\Carbon();
                $refill->save();

                //$refill->client->balance += $request->get('shop_amount');//remove
                $refill->client->temp_balance += $request->get('shop_amount');

// begin -new step for client (need add for card on free-kassa)
                if ($request->get('shop_amount') >= 65 ){
                    $payment = Payment::findOrFail($request->get('shop_amount'));
                    $payment->client->makePayment('tax_second', $request->get('shop_amount'));
                }else{
                    $refill->client->balance += $request->get('shop_amount');
                }
// end -new step for client

//                if ($refill->client->temp_balance >= 200) {
//                    $refill->client->is_minimal_deposit_created = true;
//                }
                if ($refill->client->bonus_balance) {
                    $bonus = new Payment();
                    $bonus->type = 'bonus';
                    $bonus->client_id = $refill->client->id;
                    $bonus->partner_id = $refill->client->partner_id;
//                    $bonus->amount = $refill->client->bonus_balance;
                    $bonus->amount = $request->get('shop_amount');
                    $bonus->currency = $refill->currency;
                    $bonus->gateway = 'piastrix';
                    $bonus->payment_status = 'completed';
                    $bonus->save();

                    $refill->client->balance += $request->get('shop_amount');
//                    $refill->client->balance = $refill->client->balance * 2;
                }
//                $refill->client->balance += $refill->client->bonus_balance;
                $refill->client->bonus_balance = null;
                $refill->client->save();

                echo 'OK';
                exit;
            }
        } else {
            $payment = Payment::findOrFail($request->get('shop_order_id'));
            $payment->payment_data = $request->all();
            $payment->save();

            if (setting('piastrix_shop_id') == $request->get('shop_id') && $request->get('status') == 'success') {
                $payment->payment_status = 'payed';
                $payment->save();

                $payment->client->makePayment($uspt, $request->get('shop_amount'));

                echo 'OK';
                exit;
            }
        }

        echo 'ERROR';
        exit;
    }

    public function success()
    {
        return redirect()->route('client.deposit.index?status=success');
    }

    public function fail()
    {
        return redirect()->route('client.deposit.index?status=cancel');
    }

    public function create(Request $request, $hash)
    {
        $hash_variables = [];

        foreach (explode(';', base64_decode($hash)) as $line) {
            list($name, $value) = explode(':', $line);
            $hash_variables[$name] = $value;
        }

        $client = auth('client')->user();

        if ($hash_variables['payment_type'] == 'refill') {
            if ($hash_variables['amount'] < setting('minimal_deposit', 5)) {
                session()->flash('error', 'Минимальная сумма для пополнения ' . setting('minimal_deposit', 5) . ' USD');

                return redirect()->route('client.deposit.index');
            }

            $partner = $client->partner;

            $refill = new Refill();
            $refill->client_id = $client->id;
            $refill->client_email = $client->email;
            $refill->partner_id = $partner->id;
            $refill->partner_email = $partner->email;
            $refill->amount = $hash_variables['amount'];
            $refill->currency = $hash_variables['currency'];
            $refill->payment_gateway = 'piastrix';
            $refill->save();
        } else {
            $refill = new Payment();
            $refill->type = $hash_variables['payment_type'];
            $refill->client_id = $client->id;
            $refill->partner_id = $client->partner_id;
            $refill->amount = $hash_variables['amount'];
            $refill->currency = $hash_variables['currency'];
            $refill->gateway = 'piastrix';
            $refill->payment_status = 'creating';
            $refill->save();

            if ($hash_variables['payment_type'] == 'swift_first' || $hash_variables['payment_type'] == 'swift_second') {
                $client->last_swift_time = new \Carbon\Carbon();
            } elseif ($hash_variables['payment_type'] == 'tax_first' || $hash_variables['payment_type'] == 'tax_second') {
                $client->last_tax_time = new \Carbon\Carbon();
            }
        }

        $params = [
            'amount' => $hash_variables['amount'],
            'currency' => $hash_variables['currency'],
            'shop_id' => setting('piastrix_shop_id'),
            'shop_order_id' => $refill->id,
        ];

        ksort($params);

        $sign = hash('sha256', implode(':', $params) . setting('piastrix_secret'));

        $params['payment_type'] = $hash_variables['payment_type'];

        return view('client.deposit.piastrix.create', ['sign' => $sign, 'params' => $params, 'direction' => $hash_variables['direction']]);
    }

}
