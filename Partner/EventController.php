<?php

namespace App\Http\Controllers\Partner;

use App\Models\Category;
use App\Models\Event;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EventController extends Controller
{

    public function index(Request $request)
    {
        $query = Event::orderBy('date', 'asc')
            ->where('date', '>', new \Carbon\Carbon())
            ->where('is_created_manually', true)
            ->with(['category', 'partner']);

        $filter = [
            'period' => $request->input('filter.period') ? $request->input('filter.period') : ((new Carbon())->now()->subMonth() . ' - ' . (new Carbon())->now()),
            'partner_id' => $request->input('filter.partner_id'),
        ];

        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $query->whereBetween('date', $arPeriod);

        }

        if ($filter['partner_id']) {
            $query->where('partner_id', $filter['partner_id']);
        }

        $pager = $query->paginate();

        $partners = ['' => 'Выберите из списка'];
        foreach (\App\Models\User::orderBy('name', 'asc')->get() as $partner) {
            $partners[$partner->id] = $partner->name . ' [ ID: ' . $partner->id . ']';
        }

        return view('partner.events.index', ['pager' => $pager, 'partners' => $partners, 'filter' => $filter]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new Event();

        $categories = [];
        foreach (Category::query()->get() as $category) {
            $categories[$category->id] = [
                'id' => $category->id,
                'name'=>$category->name,
                'parent_id' => $category->parent_id,
            ];
        }

        return view('partner.events.form', ['model' => $model, 'action' => 'new', 'categories' => $categories]);
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
            'category_id' => 'required',
        ]);

        $event_from_category = $this->getRandomEvent();

        $date = new \Carbon\Carbon($request->get('date'));

        $model = new Event();
        $model->name = $request->get('name');
        $model->category_id = $request->get('category_id');
        $model->partner_id = auth('user')->user()->id;
        $model->date = $date;
        $model->is_public = $request->get('is_public') ? true : false;
//        $model->is_parsed = $request->get('is_public') ? true : false;
        $model->is_created_manually = true;
        $model->is_parsed = false;
        $model->remote_id = $event_from_category->remote_id;
        $model->remote_data = $event_from_category->remote_data;
        $model->save();

        session()->flash('success', 'Информация создана');

        return redirect()->route('partner.events.index');
    }

    protected function getRandomEvent()
    {
        $event_from_category = Event::orderBy(DB::raw('RAND()'))->first();

        if (!$event_from_category || (isset($event_from_category->remote_data['bets']) && !count($event_from_category->remote_data['bets']))) {
            $event_from_category = self::getRandomEvent();
        }

        return $event_from_category;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = Event::findOrFail($id);

        $categories = [];
        foreach (Category::query()->get() as $category) {
            $categories[$category->id] = [
                'id' => $category->id,
                'name'=>$category->name,
                'parent_id' => $category->parent_id,
            ];
        }

        return view('partner.events.form', ['model' => $model, 'action' => 'edit', 'categories' => $categories]);
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
            'category_id' => 'required',
        ]);

        $model = Event::findOrFail($id);
        $model->name = $request->get('name');
        $model->category_id = $request->get('category_id');
        $model->date = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $request->get('date'));
        $model->is_public = $request->has('is_public') ? true : false;
//        $model->is_parsed = $request->has('is_public') ? true : false;
        $model->save();

        session()->flash('success', 'Информация обновлена');

        return redirect()->route('partner.events.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = Event::findOrFail($id);

        $model->delete();

        session()->flash('success', 'Запись удалена');

        return redirect()->route('partner.events.index');
    }

    public function change_fake_event($id)
    {
        $event = Event::findOrFail($id);

        $random_event = self::getRandomEvent();

        $event->remote_data = $random_event->remote_data;
//        $event->is_parsed = false;
        $event->save();

        session()->flash('success', 'Ставки обновлены');

        return redirect()->route('partner.events.edit', ['id' => $id]);
    }

}
