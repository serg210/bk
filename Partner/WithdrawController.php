<?php

namespace App\Http\Controllers\Partner;

use App\Models\SupportMessage;
use App\Models\Withdraw;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WithdrawController extends Controller
{

    public function index(Request $request)
    {
        $withdraws = Withdraw::getByPartnerId()->with('client')->orderBy('created_at', 'desc');

        $clients = ['' => 'Выберите из списка'];
        foreach (\App\Models\Client::getByPartnerId()->orderBy('email', 'asc')->get() as $client) {
            $clients[$client->id] = $client->email . ' - ' . $client->name . ' [ ID: ' . $client->id . ']';
        }

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'status' => $request->input('filter.status'),
            'period' => $request->input('filter.period', implode(' - ', $period)),
            'client_id' => $request->input('filter.client_id'),
        ];

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

        return view('partner.withdraws.index', ['pager' => $pager, 'filter' => $filter, 'clients' => $clients, 'total_amount' => $total_amount]);
    }

    public function edit($id)
    {
        $model = Withdraw::getById($id);

        return view('partner.withdraws.form', ['model' => $model]);
    }

    public function update(Request $request, $id)
    {
        $model = Withdraw::getById($id);

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
        $model->payout_address_type = $request->get('payout_address_type');
        $model->payout_address = $request->get('payout_address');
        $model->message = $request->get('message');
        $model->save();

        if($client->step == 'swift_first' && $request->get('status') == 'return'){
            $client->step = null;
            $client->save();
        }

        session()->flash('success', 'Изменения сохранены');

        return redirect()->route('partner.withdraws.index');
    }

}
