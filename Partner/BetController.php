<?php

namespace App\Http\Controllers\Partner;

use App\Models\Bet;
use App\Models\Support;
use App\Models\SupportMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BetController extends Controller
{

    public function index(Request $request)
    {
        $bets = Bet::getByPartnerId()
            ->orderBy('status_for_partner', 'asc')
            ->orderBy('created_at', 'desc')
            ->with(['event', 'event.category', 'client']);

        $clients = ['' => 'Выберите из списка'];
        foreach (\App\Models\Client::getByPartnerId()->orderBy('email', 'asc')->get() as $client) {
            $clients[$client->id] = $client->email . ' - ' . $client->name . ' [ ID: ' . $client->id . ']';
        }

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'status_for_client' => $request->input('filter.status_for_client'),
            'status_for_partner' => $request->input('filter.status_for_partner'),
//            'period' => $request->input('filter.period', implode(' - ', $period)),
            'period' => $request->input('filter.period'),
            'client_id' => $request->input('filter.client_id'),
        ];


        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $bets->whereBetween('created_at', $arPeriod);

        }

        if ($filter['status_for_client']) {
            $bets->where('status_for_client', $filter['status_for_client']);
        }
        if ($filter['status_for_partner']) {
            $bets->where('status_for_partner', $filter['status_for_partner']);
        }
        if ($filter['client_id']) {
            $bets->where('client_id', $filter['client_id']);
        }

        $pager = $bets->paginate(25);

        return view('partner.bets.index', ['pager' => $pager, 'filter' => $filter, 'clients' => $clients]);
    }

    public function edit(Request $request, $id)
    {
        $model = Bet::getById($id);
        
        return view('partner.bets.form', ['model' => $model]);
    }

    public function update(Request $request, $id)
    {
        $model = Bet::getById($id);

        $this->validate($request, [
            'amount' => 'required|numeric',
//            'win_amount' => 'required|numeric',
        ]);

        if ($model->status_for_partner == 'new' && $request->get('status_for_partner') != 'new' && $request->get('message')) {
            $support_message = new SupportMessage();
            $support_message->partner_id = $model->partner_id;
            $support_message->client_id = $model->client_id;
            $support_message->from = 'partner';
            $support_message->is_read_by_partner = true;
            $support_message->message = $request->get('message');
            $support_message->save();

            $model->client->last_support_message_at = new Carbon();
            $model->client->save();
        }

        if ($model->status_for_client == 'new' && $request->get('status_for_client') == 'win') {
//            need to round up to a larger value
            $model->client->balance += ceil($model->amount * (float)$model->data['value']);
            $model->win_amount = ceil($model->amount * (float)$model->data['value']);
//            $model->client->balance += $model->amount * (float)$model->data['value'];
            $model->client->save();
        }

        if ($model->status_for_client == 'win' && $request->get('status_for_client') == 'new') {
//            need to round up to a larger value
            $model->client->balance -= ceil($model->amount * (float)$model->data['value']);
            $model->win_amount = ceil($model->amount * (float)$model->data['value']);
            $model->client->save();
        }

        $model->amount = $request->get('amount');
//        $model->win_amount = $request->get('win_amount');
        $model->status_for_client = $request->get('status_for_client');
        $model->status_for_partner = $request->get('status_for_partner');
        $model->message = $request->get('message');
        $model->save();

        session()->flash('success', 'Информация обновлена');

        return redirect()->route('partner.bets.index');
    }

    public function destroy($id)
    {
        $model = Bet::getById($id);

        $model->client->balance += $model->amount;
        $model->client->save();

        Bet::find($id)->delete();

        return redirect()->route('partner.bets.index');
    }

}
