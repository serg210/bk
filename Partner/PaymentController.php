<?php

namespace App\Http\Controllers\Partner;

use App\Models\Payment;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

    public function index(Request $request)
    {
        $payments = Payment::with('client')->orderBy('updated_at', 'desc')->where('partner_id', auth('user')->user()->id);

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
            'period' => $request->input('filter.period', implode(' - ', $period)),
            'client_id' => $request->input('filter.client_id'),
        ];

        //$payments->whereBetween('created_at', explode(' - ', $filter['period']));
        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $payments->whereBetween('created_at', $arPeriod);

        }
        
        if ($filter['payment_status']) {
            $payments->where('payment_status', $filter['payment_status']);
        }
        
        if ($filter['client_id']) {
            $payments->where('client_id', $filter['client_id']);
        }

        $total_amount = $payments->sum('amount');

        $pager = $payments->paginate(25);

        return view('partner.payments.index', ['pager' => $pager, 'filter' => $filter, 'clients' => $clients, 'total_amount' => $total_amount]);
    }

    public function check($id)
    {
        $refill = Payment::getById($id);

        $refill->is_view = true;
        $refill->save();

        session()->flash('success', 'Отмеченно');

        return redirect()->route('partner.payment.index');
    }

}
