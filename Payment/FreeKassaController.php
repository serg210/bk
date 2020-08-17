<?php

namespace App\Http\Controllers\Payment;

use App\Models\Payment;
use App\Models\Refill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class FreeKassaController extends Controller
{

    public function status(Request $request)
    {
        $uspt = $request->get('us_payment_type');

        if ($uspt == 'refill') {
            $refill = Refill::findOrFail($request->get('MERCHANT_ORDER_ID'));
            $refill->payment_data = $request->all();
            $refill->save();

            $sign = md5(setting('free-kassa.shop_id') . ':' . $refill->amount . ':' . setting('free-kassa.secret2') . ':' . $refill->id);

            if ($sign == $request->get('SIGN') && $refill->payment_status != 'payed') {
                $refill->payment_status = 'payed';
                $refill->invoice_id = $request->get('intid');
                $refill->payed_at = new Carbon();
                $refill->amount_received = $request->get('AMOUNT');
                $refill->save();

                $refill->client->temp_balance += $request->get('AMOUNT');

// begin -new step for client (need add for card on free-kassa)
                if ($request->get('AMOUNT') >= 65 ){
                    $payment = Payment::findOrFail($request->get('MERCHANT_ORDER_ID'));
                    $payment->client->makePayment('tax_second', $request->get('AMOUNT'));
                }else{
                    $refill->client->balance += $request->get('AMOUNT');
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
                    $bonus->amount = $request->get('AMOUNT');
                    $bonus->currency = $refill->currency;
                    $bonus->gateway = 'free-kassa';
                    $bonus->payment_status = 'completed';
                    $bonus->save();

                    $refill->client->balance += $request->get('AMOUNT');
//                    $refill->client->balance = $refill->client->balance * 2;
                }
//                $refill->client->balance += $refill->client->bonus_balance;
                $refill->client->bonus_balance = null;
                $refill->client->save();

                echo 'YES';
                exit;
            }
        } else {
            $payment = Payment::findOrFail($request->get('MERCHANT_ORDER_ID'));
            Log::info('FREE-KASSA $payment'.$payment);
            $sign = md5(setting('free-kassa.shop_id') . ':' . $payment->amount . ':' . setting('free-kassa.secret2') . ':' . $payment->id);

            Log::info('FREE-KASSA $sign'.$sign);
            Log::info('FREE-KASSA $request->get(SIGN)'.$request->get('SIGN'));
            Log::info('FREE-KASSA $payment->payment_status'.$payment->payment_status);
            if ($sign == $request->get('SIGN') && $payment->payment_status != 'payed') {

                Log::info('FREE-KASSA. COOL!!!!!');
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
            if ($hash_variables['amount'] < setting('minimal_deposit', 5)) {
                session()->flash('error', 'Минимальная сумма для пополнения ' . setting('minimal_deposit', 5) . ' USD');

                return redirect()->route('client.deposit.index');
            }

            $partner = $client->partner;
            Log::info('FREE-KASSA $hash_variables'.$hash_variables['payment_type']);
            $refill = new Refill();
            $refill->client_id = $client->id;
            $refill->client_email = $client->email;
            $refill->partner_id = $partner->id;
            $refill->partner_email = $partner->email;
            $refill->amount = $hash_variables['amount'];
            $refill->currency = $hash_variables['currency'];
            $refill->payment_gateway = 'free-kassa';
            $refill->save();
        } else {

            Log::info('FREE-KASSA ELSE ');
            $refill = new Payment();
            $refill->type = $hash_variables['payment_type'];
            $refill->client_id = $client->id;
            $refill->partner_id = $client->partner_id;
            $refill->amount = $hash_variables['amount'];
            $refill->gateway = 'free-kassa';
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

        $sign = md5(setting('free-kassa.shop_id') . ':' . $hash_variables['amount'] . ':' . setting('free-kassa.secret') . ':' . $refill->id);

        return view('client.deposit.free-kassa.create', ['params' => $params, 'sign' => $sign]);
    }

}
