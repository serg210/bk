<?php

namespace App\Http\Controllers\Payment;

use App\Models\Refill;
use App\Support\Gateway\InterKassa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class InterkassaController extends Controller
{

    public function status(Request $request)
    {
        $refill = Refill::findOrFail($request->get('ik_pm_no'));

        $gateway = new InterKassa($refill, $request->get('ik_pw_via'));
        if ($gateway->check($request)) {
            $refill->payment_status = 'payed';
            $refill->payment_data = $request->all();
            $refill->invoice_id = $request->get('ik_inv_id');
            $refill->payed_at = new Carbon();
            $refill->save();

            echo 'OK';
        }

        echo 'ERROR';
    }

    public function success()
    {
        session()->flash('success', 'Вы успешно пополнили свой счет. Деньги будут зачислены в ближайшее время.');

        return redirect('/');
    }

    public function fail()
    {
        session()->flash('error', 'Вы отменили оплату счета.');

        return redirect('/');
    }

    public function pending()
    {
        session()->flash('warning', 'Ожидается подтверждение платежа.');

        return redirect('/');
    }

    public function create(Request $request, $hash)
    {
        $hash_variables = [];
        /**
         * @description parse and fix incoming variables
         */
        foreach (explode(';', base64_decode($hash)) as $line) {
            list($name, $value) = explode(':', $line);
            $hash_variables[$name] = $value;
        }

        $client = auth('client')->user();

        $partner = $client->partner;

        $refill = new Refill();
        $refill->client_id = $client->id;
        $refill->client_email = $client->email;
        $refill->partner_id = $partner->id;
        $refill->partner_email = $partner->email;
        $refill->amount = $hash_variables['amount'];
        $refill->currency = $hash_variables['currency'];
        $refill->payment_gateway = 'interkassa';
        $refill->save();

        $gateway = new InterKassa($refill, $hash_variables['direction']);

        return view('client.deposit.interkassa.create', ['gateway' => $gateway]);
    }

}
