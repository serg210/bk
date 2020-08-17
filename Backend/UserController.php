<?php

namespace App\Http\Controllers\Backend;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = User::orderBy('last_visit_at', 'desc');

        $filter = [
            'partner_id' => $request->input('filter.partner_id'),
            'period' => $request->input('filter.period'),
        ];

        if ($filter['partner_id']) {
            $query->where('id', $filter['partner_id']);
        }

//        if ($filter['period']) {
//            $query->whereBetween('last_visit_at', explode(' - ', $filter['period']));
//        }
        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $query->whereBetween('last_visit_at', $arPeriod);

        }

        if (!auth('user')->user()->is_super) {
            $query->where('is_super', false);
        }

        $partners = ['' => 'Выберите из списка'];

        foreach (\App\Models\User::orderBy('name', 'asc')->get() as $partner) {
            $partners[$partner->id] = $partner->name . ' [ ID: ' . $partner->id . ']';
        }

        $pager = $query->paginate(25);

        return view('backend.users.index', ['pager' => $pager, 'filter' => $filter, 'partners' => $partners]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new User();

        return view('backend.users.form', ['action' => 'new', 'model' => $model]);
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
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed'
        ]);

        $model = new User();
        $model->name = $request->get('name');
        $model->email = $request->get('email');
        $model->roles = $request->get('roles', 'partner');
        $model->password = \Hash::make($request->get('password'));
        $model->save();

        session()->flash('success', 'Информация сохранена');

        return redirect()->route('backend.users.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = User::findOrFail($id);

        if ($model->is_super && !auth('user')->user()->is_super) {
            session()->flash('error', 'Произошла ошибка');

            return redirect()->route('backend.users.index');
        }

        return view('backend.users.form', ['action' => 'edit', 'model' => $model]);
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
        $model = User::findOrFail($id);

        $model->name = $request->get('name');
        $model->email = $request->get('email');
        $model->roles = $request->get('roles', 'partner');
        if ($request->get('password')) {
            $model->password = \Hash::make($request->get('password'));
        }
        $model->save();

        session()->flash('success', 'Информация сохранена');

        return redirect()->route('backend.users.index');
    }

    public function auth($id)
    {
        if (!auth('user')->user()->is_super) {
            session()->flash('error', 'Произошла ошибка');

            return redirect()->route('backend.users.index');
        }

        $model = User::findOrFail($id);

        auth('user')->login($model);

        return redirect('/');
    }
}
