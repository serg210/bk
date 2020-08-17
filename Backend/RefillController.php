<?php

namespace App\Http\Controllers\Backend;

use App\Models\Refill;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RefillController extends Controller
{

    public function index(Request $request)
    {
        $refills = Refill::with(['client', 'partner'])->orderBy('updated_at', 'desc');

        $partners = ['' => 'Выберите из списка'];

        foreach (\App\Models\User::orderBy('name', 'asc')->get() as $partner) {
            $partners[$partner->id] = $partner->name . ' [ ID: ' . $partner->id . ']';
        }

        $clients = ['' => 'Выберите из списка'];

        foreach (\App\Models\Client::orderBy('email', 'asc')->get() as $client) {
            $clients[$client->id] = $client->email . ' - ' . $client->name . ' [ ID: ' . $client->id . ']';
        }

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->addDay()->toDateTimeString(),
        ];

        $filter = [
            'payment_status' => $request->input('filter.payment_status', 'payed'),
//            'process_status' => $request->input('filter.process_status', 'payed'),
            'period' => $request->input('filter.period', implode(' - ', $period)),
            'partner_id' => $request->input('filter.partner_id'),
            'client_id' => $request->input('filter.client_id'),
        ];

        $refills->where('payment_status', $filter['payment_status']);

        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $refills->whereBetween('created_at', $arPeriod);

        }

        if ($filter['partner_id']) {
            $refills->where('partner_id', $filter['partner_id']);
        }

//        if ($filter['process_status']) {
//            $refills->where('process_status', $filter['process_status']);
//        }

        if ($filter['client_id']) {
            $refills->where('client_id', $filter['client_id']);
        }

        $total_amount = $refills->sum('amount');

        $pager = $refills->paginate(25);

        return view('backend.refills.index', ['pager' => $pager, 'filter' => $filter, 'partners' => $partners, 'clients' => $clients, 'total_amount' => $total_amount]);
    }

    public function check($id)
    {
        $model = Refill::findOrFail($id);

        $model->process_status = 'completed';
        $model->save();

        session()->flash('success', 'Найдено');

        return redirect()->route('backend.refills.index');
    }

}
