<?php

namespace App\Http\Controllers\Client;

use App\Models\Bet;
use App\Models\Statistic;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BetsController extends Controller
{

    public function index($type)
    {
        $bets = Bet::orderBy('created_at', 'desc')->where('client_id', auth('client')->user()->id);

        $date = \Carbon\Carbon::now()->subHour(2);

        if ($type == 'current') {
            $bets->whereHas('event', function ($query) use ($date) {
                $query->where('date', '>=', $date);
            });
        } else {
            $bets->whereHas('event', function ($query) use ($date) {
                $query->where('date', '<', $date);
            });
        }

        $pager = $bets->paginate(4);

        return view('client.bets.index', ['pager' => $pager]);
    }

    public function place(Request $request, $type)
    {
        $client = auth('client')->user();

        if (!auth('client')->check()) {
            return response()->json([
                'status' => 'error',
                'message' => __('<b style="color:#000000">Для совершения ставок вам необходимо авторизоваться.</b>'),
            ]);
        }

        $bets_amount = 0;
        if ($type == 'single') {
            $bets = $request->get('bets');
            $bets_amount = array_sum($bets);

            if (!$bets_amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('<b style="color:#000000">Вам необходимо указать сумму ставки.</b>'),
                ]);
            }

            if ($bets_amount>2000) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('<b style="color:#000000">Сумма одной ставки не должна превышать 2000$.</b>'),
                ]);
            }

            if ($bets_amount < $client->minimal_bet_amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Минимальная сумма ставки ' . $client->minimal_bet_amount . ' USD'),
                ]);
            }
        } elseif ($type == 'express' && session()->has('cart')) {
            $bets_amount = count(session('cart')) * $request->get('bet');

            if (!$request->get('bet')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('<b style="color:#000000">Вам необходимо указать сумму ставки.</b>'),
                ]);
            }

            if ($request->get('bet') > 2000) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('<b style="color:#000000">Сумма одной ставки не должна превышать 2000$.</b>'),
                ]);
            }

            if ($request->get('bet') < $client->minimal_bet_amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Минимальная сумма ставки ' . $client->minimal_bet_amount . ' USD'),
                ]);
            }
        }

        if ($client->balance < $bets_amount) {
            return response()->json([
                'status' => 'error',
                'message' => __('<b style="color:#000000">У вас недостаточно средств на балансе.</b>') . '<br>' . __('<b style="color:#000000">Вам необходимо <a  href="' . route('client.deposit.index') . '">пополнить</a> баланс.</b>'),
            ]);
        }

        /**
        Проверяем были ли ставки на событие раньше
         */
        $events_ids = [];
        foreach (session('cart') as $item) {
            if(in_array($item['id'], $events_ids)){
                return response()->json([
                    'status' => 'error',
                    'message' => __('<b style="color:#000000">Уважаемый клиент,  Вы уже совершили ставку на данное событие.</b>'),
                ]);
            }

            $events_ids[] = $item['id'];
        }
        $arBets = Bet::where('client_id', auth('client')->user()->id)->where('event_id', $events_ids)->get();

        if(count($arBets)){
            return response()->json([
                'status' => 'error',
                'message' => __('<b style="color:#000000">Уважаемый клиент,  Вы уже совершили ставку на данное событие.</b>'),
            ]);
        }




        if ($client->partner) {
            $partner_email = $client->partner->email;
        } else {
            $partner_email = null;
        }

        $client->balance -= $bets_amount;


        $bets_count = $client->bets_count;
        $current_bets_count = 0;
        $current_bets_count_amount = 0;

        if ($type == 'single') {
            // check event actuality
            foreach (session('cart') as $hash => $bet_info) {
                if (isset($bets[$hash])) {
                    $bet = new Bet();
                    $bet->partner_id = $client->partner_id;
                    $bet->client_id = $client->id;
                    $bet->partner_email = $partner_email;
                    $bet->client_email = $client->email;
                    $bet->event_id = $bet_info['id'];
                    $bet->amount = $bets[$hash];
                    $bet->data = $bet_info;
                    $bet->status_for_partner = 'new';
                    $bet->status_for_client = 'new';
                    $bet->save();

                    session()->remove('cart.' . $hash);

                    $bets_count++;

                    $current_bets_count++;
                    $current_bets_count_amount += $bets[$hash];
                }
            }
        } elseif ($type == 'express') {
            // check event actuality
            foreach (session('cart') as $hash => $bet_info) {
                $bet = new Bet();
                $bet->partner_id = $client->partner_id;
                $bet->client_id = $client->id;
                $bet->partner_email = $partner_email;
                $bet->client_email = $client->email;
                $bet->event_id = $bet_info['id'];
                $bet->amount = $request->get('bet');
                $bet->data = $bet_info;
                $bet->status_for_partner = 'new';
                $bet->status_for_client = 'new';
                $bet->save();

                $bets_count++;

                $current_bets_count++;
                $current_bets_count_amount += $bets[$hash];
            }

            session()->forget('cart');
        }

        $client->bets_count = $bets_count;

        $client->save();

        Statistic::write($client, 'bets', $current_bets_count, $current_bets_count_amount);

        return response()->json([
            'status' => 'success',
            'message' => __('<b style="color:#000000">Уважаемый клиент, Ваша ставка принята!</b>'),
        ]);
    }

}
