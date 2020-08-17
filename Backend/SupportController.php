<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class SupportController extends Controller
{

    public function switch_payment_gateway($gateway = null)
    {
        if ($gateway) {
            session()->flash('success', 'Платежный шлюз изменен');

            setting_set('payment.gateway', $gateway);

            return redirect()->route('backend.support.switch_payment_gateway');
        }

        $payment_gateways = [
            'free-kassa' => 'Free-Kassa',
            'piastrix' => 'Piastrix',
            'my-kassa' => 'My-Kassa',
        ];


        return view('backend.support.switch_payment_gateway', ['payment_gateways' => $payment_gateways]);
    }

    public function parser(Request $request, $type = null)
    {
        $response = null;

        if ($request->isMethod('post')) {
            switch ($type) {
                case 'single':
                    $this->validate($request, [
                        'id' => 'required|numeric'
                    ]);

                    $response = Artisan::call('parser:mostbet', [
                        '--dev' => true,
                        '--force' => true,
                        '--type' => 'single',
                        '--id' => $request->get('id'),
                    ]);

                    session('success', 'Все отлично');

                    break;
                case 'multiple':
                    $this->validate($request, [
                        'id_from' => 'required|numeric',
                        'id_to' => 'required|numeric'
                    ]);

                    $response = Artisan::call('parser:mostbet', [
                        '--dev' => true,
                        '--force' => true,
                        '--type' => 'multiple',
                        '--from' => $request->get('id_from'),
                        '--to' => $request->get('id_to'),
                    ]);

                    session('success', 'Все отлично');

                    break;
            }
        }
        return view('backend.support.parser', ['response' => $response]);
    }

}
