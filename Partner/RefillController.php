<?php

namespace App\Http\Controllers\Partner;

use App\Models\Refill;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RefillController extends Controller
{

    public function index(Request $request)
    {
        $refills = Refill::getByPartnerId()->with('client')->orderBy('updated_at', 'desc');

        $clients = ['' => 'Выберите из списка'];

        foreach (\App\Models\Client::getByPartnerId()->orderBy('email', 'asc')->get() as $client) {
            $clients[$client->id] = $client->email . ' - ' . $client->name . ' [ ID: ' . $client->id . ']';
        }

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'payment_status' => $request->input('filter.payment_status', 'payed'),
//            'process_status' => $request->input('filter.process_status', 'payed'),
            'period' => $request->input('filter.period', implode(' - ', $period)),
            'client_id' => $request->input('filter.client_id'),
        ];


        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $refills->whereBetween('created_at', $arPeriod);
            
        }

        if ($filter['payment_status']) {
            $refills->where('payment_status', $filter['payment_status']);
        }
        
//        if ($filter['process_status']) {
//            $refills->where('process_status', $filter['process_status']);
//        }

        if ($filter['client_id']) {
            $refills->where('client_id', $filter['client_id']);
        }

        $total_amount = $refills->sum('amount');

        $pager = $refills->paginate();

        return view('partner.refills.index', ['pager' => $pager, 'filter' => $filter, 'clients' => $clients, 'total_amount' => $total_amount]);
    }

    public function edit(Request $request, $id)
    {
        $refill = Refill::getById($id);

        if ($request->isMethod('post')) {

            $refill = Refill::getById($id);
            $refill->process_status = $request->get('process_status');
            $refill->save();

            session()->flash('success', 'Сохранено');

            return redirect()->route('partner.refills.edit', ['id' => $id]);
        }

        return view('partner.refills.edit', ['refill' => $refill]);
    }

}
