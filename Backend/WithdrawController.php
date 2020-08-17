<?php

namespace App\Http\Controllers\Backend;

use App\Models\SupportMessage;
use App\Models\Withdraw;
use App\Support\Gateway\Piastrix;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WithdrawController extends Controller
{

    public function index(Request $request)
    {
        $withdraws = Withdraw::with('client')->orderBy('created_at', 'desc');

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'status' => $request->input('filter.status'),
            'period' => $request->input('filter.period', implode(' - ', $period)),
            'client_id' => $request->input('filter.client_id'),
        ];

        //$withdraws->whereBetween('created_at', explode(' - ', $filter['period']));
        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $withdraws->whereBetween('created_at', $arPeriod);

        }

        if ($filter['status']) {
            $withdraws->where('status', $filter['status']);
        }

        if ($filter['client_id']) {
            $withdraws->where('client_id', $filter['client_id']);
        }

        $total_amount = $withdraws->sum('payout_amount');

        $pager = $withdraws->paginate(25);

        return view('backend.withdraws.index', ['pager' => $pager, 'filter' => $filter, 'total_amount' => $total_amount]);
    }

    public function edit($id)
    {
        $model = Withdraw::findOrFail($id);

        return view('backend.withdraws.form', ['model' => $model]);
    }

    public function update(Request $request, $id)
    {

        $model = Withdraw::findOrFail($id);

        if ($model->status != $request->get('status')) {
            if ($model->is_processed == false) {
                $client = $model->client;
                $client->balance += $model->payout_amount;
                $client->freezed_amount -= $model->payout_amount;
                $client->save();

                if ($request->get('message')) {
                    $support_message = new SupportMessage();
                    $support_message->partner_id = $model->partner_id;
                    $support_message->client_id = $model->client_id;
                    $support_message->from = 'partner';
                    $support_message->is_read_by_partner = true;
                    $support_message->message = $request->get('message');
                    $support_message->save();
                }
            } elseif ($request->get('status') == 'return') {
                $client = $model->client;
                if ($model->payout_amount >= $client->freezed_amount) {
                    $model->payout_amount = $client->freezed_amount;
                }
                $client->balance += $model->payout_amount;
                $client->freezed_amount -= $model->payout_amount;
                $client->step = null;
                $client->save();
            }
            $model->is_processed = true;
        }

        $model->status = $request->get('status');
        $model->payout_address = $request->get('payout_address');
        $model->payout_address_type = $request->get('payout_address_type');
        $model->message = $request->get('message');
        $model->is_real_payment = $request->get('is_real_payment', false);
        $model->save();

        session()->flash('success', 'Изменения сохранены');

        if($request->action && $request->action == 'make_payment'){
            return $this->makePayment($request, $id);
        }

        return redirect()->route('backend.withdraws.index');
    }

    public function makePayment(Request $request, $id){
        $model = Withdraw::findOrFail($id);
        if($model->is_real_payment || $model->payment_system_status){
            session()->flash('error', 'Платеж уже совершен');
            return view('backend.withdraws.form', ['model' => $model]);
        }
        if ($model->created_at < '2019-04-15'){
            session()->flash('error', 'Функция доступна только для заявок после 2019-04-15');
            return view('backend.withdraws.form', ['model' => $model]);
        }

        $payway = '';
        switch (trim($request->payout_address_type)){
            case 'Яндекс.Деньги' : $payway = 'yamoney_rub';
                break;
            case 'QIWI' : $payway = 'qiwi_usd';
                break;
            case 'Visa/MasterCard' : $payway = 'card_rub';
                break;
            case 'Payeer' : $payway = 'payeer_usd';
                break;
            case 'PerfectMoney' : $payway = 'perfectmoney_usd';
                break;
//            case 'Skrill' : $payway = 'PerfectMoney';
//                break;
//            case 'W1' : $payway = 'PerfectMoney';
//                break;
//            case 'Bitcoin Cash' : $payway = 'PerfectMoney';
//               break;
            default : $payway = '';
                session()->flash('error', 'Метод вывода не поддерживается в автоматическом режиме');
                return view('backend.withdraws.form', ['model' => $model]);
                break;
        }

        if(!$model->client->countSwift() || !$model->client->countTax()){
            session()->flash('error', 'Клиент еще не оплатил комиссию или свифт');
            return redirect()->route('backend.withdraws.edit', $id);
        }

        $params = [
            "account" => str_replace(' ', '', trim($request->payout_address)),
            'payway' => $payway,
            'shop_currency' => 840,
            "amount" => setting('withdraw.amount', 10)
        ];

        $piastrix = new Piastrix();

        $response = $piastrix->setParams($params)->withdrawTry();
        $piastrix->clear();

        if($response['error_code'] || !$response['result']){
            session()->flash('error', $response['error_code'].': '.$response['message']);
            return view('backend.withdraws.form', ['model' => $model]);
        }


        $response = $piastrix->setParams($params)->checkAccount();
        $piastrix->clear();




        if($response['error_code'] || !$response['result']){
            session()->flash('error', $response['error_code'].': '.$response['message']);
            return view('backend.withdraws.form', ['model' => $model]);
        }
        if($response['result'] && ! $response['data']['result']){
            session()->flash('error', 'Аккаунт не действителен или недоступен для пополнения');
            return view('backend.withdraws.form', ['model' => $model]);
        }


        $params['shop_payment_id'] = $model->id;

        $response = $piastrix->setParams($params)->withdraw();
        $piastrix->clear();



        if($response['error_code'] || !$response['result']){
            session()->flash('error', $response['error_code'].': '.$response['message']);
            return view('backend.withdraws.form', ['model' => $model]);
        }

        if($response['result']){
            $model->payment_system_status = $response['data']['status'];
        }
        $arFinalStatus = [5,6,10,11];
        $i = 0;
        if(!in_array($model->payment_system_status, $arFinalStatus)){
            while (!in_array($model->payment_system_status, $arFinalStatus) || $i < 3){
                $response = $piastrix->setParams($params)->withdrawStatus();
                if($response['result']){
                    $model->payment_system_status = $response['data']['status'];
                }
                sleep(10);
                $i++;
            }
        }

        switch($response['result']['data']['status']){
            case 1:
                session()->flash('success', 'Платеж создан, ожидает обработки');
                break;
            case 2:
                session()->flash('success', 'Ожидает ручного подтверждения оператора');
                break;
            case 3:
                session()->flash('success', 'Отправлен на платежную систему');
                break;
            case 4:
                session()->flash('success', 'Ошибка при выплате на стороне платежной системы');
                break;
            case 5:
                session()->flash('success', 'Платеж успешно выполнен');
                $model->is_real_payment = 1;
                $model->status = 'completed';
                $client = $model->client;
                $client->freezed_amount -= $model->payout_amount;
                $client->save();
                break;
            case 6:
                session()->flash('success', 'Отклонен на стороне платежной системы');
                break;
            case 7:
                session()->flash('success', 'Подтвержден оператором и ожидает проводки');
                break;
            case 9:
                session()->flash('success', 'Сетевая ошибка на стороне платежной системы');
                break;
            case 10:
                session()->flash('success', 'Вывод отменен вручную на стороне системы Piastrix');
                break;
            case 11:
                session()->flash('success', 'Успешный вывод отменен вручную на стороне системы Piastrix');
                break;
        }

        $model->payment_system_created = Carbon::now();

        $model->save();

        return view('backend.withdraws.form', ['model' => $model]);

    }

}
