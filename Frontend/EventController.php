<?php

namespace App\Http\Controllers\Frontend;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{

    public function index(Request $request,$category_id = null)
    {
        $id = $request->id;

        $client = auth('client')->user();

//        $test = Cache::put('test', [rand()], 300);
//        dd(Cache::has('test'), Cache::has(rand()), Cache::get(rand()), Cache::get('test'));
//
//        $result = Category::withDepth()->having('depth', '=', 2)->get();
//        dd($result->toArray());

//        $date = Carbon::now(new \DateTimeZone('Europe/Moscow'));
//        $date = Carbon::now();
        $date = Carbon::now()->subDay();

//        dd($date->subHours(12)->toDateTimeString());
//        dd($date->toDateTimeString());

        $query = Event::orderBy('date', 'DESC')->with('category')
            ->where('is_public', true)
//            ->where('is_parsed', true)
            ->where('date', '>=', $date)
//            ->whereHas('category', function ($query) {
//                $query->where('is_for_parse', true);
//            })
        ;
        if ($id != null){
            $query=$query->where('id',$id);
        }

        // @todo: group by category

        if ($category_id) {
            $parent_category = Category::where('id', $category_id)->firstOrFail();
            $category_ids = array_merge([$parent_category->id], $parent_category->descendants()->pluck('id')->toArray());

            $query->whereIn('category_id', $category_ids);
        }
        $pager = $query->paginate();

        return view('frontend.event.index', ['pager' => $pager,'client' => $client]);
    }

    public function show($cateogry_id, $id)
    {
        $event = Event::findOrFail($id);

        return view('frontend.event.show', [
            'event' => $event,
        ]);
    }

    public function update(Request $request,$category_id = null)
    {
        $id = $request->id;

        $client = auth('client')->user();
//        dd($client->is_show_status_menu);
        $client = auth('client')->user();
        $client->is_show_registration_notice = false;
        $client->save();

        $date = Carbon::now()->subDay();


        $query = Event::orderBy('date', 'DESC')->with('category')
            ->where('is_public', true)
            ->where('date', '>=', $date);
        if ($id != null){
            $query=$query->where('id',$id);
        }

        // @todo: group by category

        if ($category_id) {
            $parent_category = Category::where('id', $category_id)->firstOrFail();
            $category_ids = array_merge([$parent_category->id], $parent_category->descendants()->pluck('id')->toArray());

            $query->whereIn('category_id', $category_ids);
        }
        $pager = $query->paginate();

        return view('frontend.event.index', ['pager' => $pager,'client' => $client]);
    }


}
