<?php

namespace App\Http\Controllers\Partner;

use App\Models\TrafficFlow;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TrafficFlowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $traffic_flows = TrafficFlow::getByPartnerId();

        $pager = $traffic_flows->paginate();

        return view('partner.traffic_flows.index', compact('pager'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new TrafficFlow();

        return view('partner.traffic_flows.form', ['model' => $model, 'action' => 'new']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
//            'minimal_deposit_amount' => 'numeric',
//            'minimal_bet_amount' => 'numeric',
//            'bonus' => 'numeric',
        ]);

        $model = new TrafficFlow();
        $model->partner_id = auth('user')->user()->id;
        $model->name = $request->get('name');
        $model->minimal_deposit_amount = $request->get('minimal_deposit_amount');
        $model->minimal_bet_amount = $request->get('minimal_bet_amount');
        $model->bonus = $request->get('bonus');
        $model->code = strtoupper(str_random(8));
        $model->save();

        session()->flash('success', 'Информаиця сохранена');

        return redirect()->route('partner.traffic_flows.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = TrafficFlow::getById($id);

        return view('partner.traffic_flows.form', ['model' => $model, 'action' => 'edit']);
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
//            'minimal_deposit_amount' => 'numeric',
//            'minimal_bet_amount' => 'numeric',
//            'bonus' => 'numeric',
        ]);

        $model = TrafficFlow::getById($id);
        $model->partner_id = auth('user')->user()->id;
        $model->name = $request->get('name');
        $model->minimal_deposit_amount = $request->get('minimal_deposit_amount');
        $model->minimal_bet_amount = $request->get('minimal_bet_amount');
        $model->bonus = $request->get('bonus');
        $model->save();

        session()->flash('success', 'Информация сохранена');

        return redirect()->route('partner.traffic_flows.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = TrafficFlow::getById($id);

        $model->delete();

        session()->flash('success', 'Информация удалена');

        return redirect()->route('partner.traffic_flows.index');
    }
}
