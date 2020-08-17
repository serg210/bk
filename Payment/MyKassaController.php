<?php

namespace App\Http\Controllers\Payment;

use App\Models\Payment;
use App\Models\Refill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MyKassaController extends Controller
{

    public function status(Request $request)
    {
        $uspt = $request->get('us_payment_type');

        if ($uspt == 'refill') {
            $refill = Refill::findOrFail($request->get('MERCHANT_ORDER_ID'));
            $refill->payment_data = $request->all();
            $refill->save();

            $sign = md5(setting('my-kassa.shop_id') . ':' . $request->get('AMOUNT') . ':' . setting('my-kassa.secret') . ':' . $refill->id);

            if ($sign == $request->get('SIGN')) {
                $refill->payment_status = 'payed';
                $refill->invoice_id = $request->get('MYKASSA_ID');
                $refill->payed_at = new Carbon();
                $refill->amount_received = $request->get('AMOUNT');
                $refill->save();

                $refill->client->balance += $request->get('AMOUNT');
                $refill->client->temp_balance += $request->get('AMOUNT');
//                if ($refill->client->temp_balance >= 200) {
//                    $refill->client->is_minimal_deposit_created = true;
//                }
                $refill->client->save();

                echo 'YES';
                exit;
            }
        } else {
            $payment = Payment::findOrFail($request->get('MERCHANT_ORDER_ID'));

            $sign = md5(setting('my-kassa.shop_id') . ':' . $payment->amount . ':' . setting('my-kassa.secret2') . ':' . $payment->id);

            if ($sign == $request->get('SIGN')) {
                $payment->client->makePayment($uspt, $request->get('AMOUNT'));
                $payment->payment_data = $request->all();
                $payment->payment_status = 'payed';
                $payment->save();

                echo 'YES';
                exit;
            }
        }

        echo 'NO';
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
            if ($hash_variables['amount'] < $client->minimal_deposit_amount) {
                session()->flash('error', 'Минимальная сумма для пополнения ' . $client->minimal_deposit_amount . ' USD');

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
            $refill->payment_gateway = 'my-kassa';
            $refill->save();
        } else {
            $refill = new Payment();
            $refill->type = $hash_variables['payment_type'];
            $refill->client_id = $client->id;
            $refill->partner_id = $client->partner_id;
            $refill->amount = $hash_variables['amount'];
            $refill->gateway = 'my-kassa';
            $refill->payment_status = 'creating';
            $refill->save();

            if ($hash_variables['payment_type'] == 'swift_first' || $hash_variables['payment_type'] == 'swift_second') {
                $client->last_swift_time = new Carbon();
            } elseif ($hash_variables['payment_type'] == 'tax_first' || $hash_variables['payment_type'] == 'tax_second') {
                $client->last_tax_time = new Carbon();
            }
        }

        $params = [
            'amount' => $hash_variables['amount'],
            'currency' => $hash_variables['currency'],
            'payment_type' => $hash_variables['payment_type'],
            'order_id' => $refill->id,
        ];

        $sign = md5(setting('my-kassa.shop_id') . ':' . $hash_variables['amount'] . ':' . setting('my-kassa.secret') . ':' . $refill->id);

        return view('client.deposit.my-kassa.create', ['params' => $params, 'sign' => $sign]);
    }

}
