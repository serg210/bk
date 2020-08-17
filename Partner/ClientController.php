<?php

namespace App\Http\Controllers\Partner;

use App\Models\Client;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Client::getByPartnerId()->orderBy('created_at', 'desc');

        $filter = [
            'client_id' => $request->input('filter.client_id'),
            'name' => $request->input('filter.name'),
            'id' => $request->input('filter.id'),
            'bets_count' => $request->input('filter.bets_count'),
        ];

        if ($filter['id']) {
            $query->where('id', $filter['id']);
        }

        if ($filter['bets_count']) {
            $query->where('bets_count', $filter['bets_count']);
        }

        if ($filter['client_id']) {
            $query->where('id', $filter['client_id']);
        }
        if ($filter['name']) {
            $query->where('name', 'like', '%' . $filter['name'] . '%');
        }

        $clients = ['Выберите из списка'];
        foreach (Client::where('partner_id', auth('user')->user()->id)->orderby('email', 'asc')->get() as $client) {
            $clients[$client->id] = '[ ID: ' . $client->id . ' ] ' . $client->name . ' ( ' . $client->email . ' )';
        }

        $pager = $query->paginate();

        return view('partner.clients.index', ['pager' => $pager, 'filter' => $filter, 'clients' => $clients]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = Client::getById($id);

        return view('partner.clients.form', ['model' => $model]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
        ]);

        $model = Client::getById($id);
        $model->name = $request->get('name');
        $model->email = $request->get('email');
        $model->balance = $request->get('balance');
        $model->freezed_amount = $request->get('freezed_amount');
        $model->bets_count = $request->get('bets_count');
        $model->swift_count = $request->get('swift_count');
        $model->tax_count = $request->get('tax_count');
        $model->status = $request->get('status');
        $model->is_tax_payed = $request->get('is_tax_payed') ? true : false;
        $model->is_swift_payed = $request->get('is_swift_payed') ? true : false;
        $model->is_show_status_menu = $request->get('is_show_status_menu') ? true : false;
        $model->is_status_deposit_created = $request->get('is_status_deposit_created') ? true : false;


        if($request->get('show_account_activation') == true){
            $model->step = null;
        }

        if($request->get('show_form_first') == true){
            $model->step = 'account_activation';
        }

        if($request->get('show_swift_first') == true){
            $model->step = 'swift_first';
        }

        if($request->get('show_tax_first') == true){
            $model->step = 'tax_first';
        }

        if($request->get('show_tax_first_wait') == true){
            $model->step = 'tax_first_wait';
        }

        if($request->get('show_status') == true){
            $model->step = 'status';
        }

        if($request->get('show_deposit') == true){
            $model->step = 'pre_swift_second';
            $model->temp_balance = 190;
        }

        if($request->get('show_form_second') == true){
            $model->step = 'pre_swift_second';
            $model->temp_balance = 200;
        }

        if($request->get('show_swift_second') == true){
            $model->step = 'swift_second';
            $model->temp_balance = 200;
        }

        if($request->get('show_tax_second') == true){
            $model->step = 'tax_second';
            $model->temp_balance = 200;
        }

        if($request->get('show_insurance') == true){
            $model->step = 'insurance';
            $model->temp_balance = 265;
        }

        if($request->get('show_complete') == true){
            $model->step = 'complete';
        }

        if ($request->get('password') && $request->get('password') == $request->get('password_confirmation')) {
            $model->password = \Hash::make($this->argument('password'));
        }
        if (!$request->get('is_show_status_menu')) {
            $model->status = null;
        }
        $model->save();

        if ($request->get('bonus')) {
            $bonus = new Payment();
            $bonus->type = 'bonus';
            $bonus->client_id = $model->id;
            $bonus->partner_id = $model->partner_id;
            $bonus->amount = $request->get('bonus');
            $bonus->payment_status = 'completed';
            $bonus->save();

            $model->balance = $request->get('balance');
            $model->balance += $request->get('bonus');
            $model->save();
        }

        if($model->step == 'swift_first' && $model->freezed_amount == 0){
            $model->step = null;
            $model->save();
        }

        session()->flash('success', 'Информация обновлена');

        return redirect()->route('partner.clients.index');
    }

    public function auth($id)
    {
        $model = Client::getById($id);

        auth('client')->login($model);

        return redirect('/');
    }
}
