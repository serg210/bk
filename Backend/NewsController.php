<?php

namespace App\Http\Controllers\Backend;

use App\Models\News;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NewsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $news = News::orderBy('created_at', 'desc');

        $pager = $news->paginate();

        return view('backend.news.index', ['pager' => $pager]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new News();

        return view('backend.news.form', ['model' => $model, 'action' => 'new']);
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
            'title' => 'required',
            'lead' => 'required',
            'body' => 'required',
            'image' => 'required|image',
        ]);

        $model = new News();
        $model->title = $request->get('title');
        $model->lead = $request->get('lead');
        $model->body = $request->get('body');
//        $model->image = $request->get('image');
        $model->save();

        return redirect()->route('backend.news.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = News::findOrFail($id);

        return view('backend.news.form', ['model' => $model, 'action' => 'edit']);
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
            'title' => 'required',
            'lead' => 'required',
            'body' => 'required',
            'image' => 'image',
        ]);

        $model = News::findOrFail($id);
        $model->title = $request->get('title');
        $model->lead = $request->get('lead');
        $model->body = $request->get('body');
//        $model->image = $request->get('image');
        $model->save();

        session()->flash('success', 'Информация сохранена');

        return redirect()->route('backend.news.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = News::findOrFail($id);

        $model->delete();

        session()->flash('success', 'Запись удалена');

        return redirect()->route('backend.news.index');
    }
}
