<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Mitris\Core\Models\Setting;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth('user')->user()->is_super) {
            session()->flash('error', 'Произошла ошибка');

            return redirect()->route('backend.events.index');
        }

        $settings = Setting::orderBy('name', 'asc');

        $pager = $settings->paginate(50);

        return view('backend.settings.index', ['pager' => $pager]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new Setting();

        return view('backend.settings.form', ['model' => $model, 'action' => 'new']);
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
            'value' => 'required',
        ]);

        Setting::create($request->all());

        session()->flash('success', 'Запись создана');

        return redirect()->route('backend.settings.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth('user')->user()->is_super) {
            session()->flash('error', 'Произошла ошибка');

            return redirect()->route('backend.events.index');
        }

        $model = Setting::findOrFail($id);

        return view('backend.settings.form', ['model' => $model, 'action' => 'edit']);
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
        ]);

        $model = Setting::findOrFail($id);

        $model->update($request->all());

        session()->flash('success', 'Запись обновлена');

        return redirect()->route('backend.settings.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = Setting::findOrFail($id);

        $model->delete();

        session()->flash('success', 'Запись удалена');

        return redirect()->route('backend.settings.index');
    }
}
