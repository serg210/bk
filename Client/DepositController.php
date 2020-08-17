<?php

namespace App\Http\Controllers\Client;

use App\Models\Payment;
use App\Models\Refill;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DepositController extends Controller
{

    public function index()
    {
        $client = auth('client')->user();

        $refills = Refill::orderBy('created_at', 'desc')->where('client_id', $client->id)
            ->where('payment_status', 'payed');

        $refills_pager = $refills->paginate();

        $payments = Payment::orderBy('created_at', 'desc')->where('client_id', $client->id)
            ->whereIn('payment_status', ['payed', 'completed']);

        $payments_pager = $payments->paginate();

        return view('client.deposit.index', ['refills_pager' => $refills_pager, 'payments_pager' => $payments_pager, 'client' => $client]);
    }

}
