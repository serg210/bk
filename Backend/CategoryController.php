<?php

namespace App\Http\Controllers\Backend;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tree = Category::get()->toTree();

        return view('backend.categories.index', ['tree' => $tree]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new Category();

        $categories = [
            '' => 'Корневая категория',
        ];
        foreach (Category::withDepth()->get()->toFlatTree() as $category) {
            $categories[$category->id] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $category->depth) . $category->name;
        }

        return view('backend.categories.form', ['model' => $model, 'categories' => $categories, 'action' => 'new']);
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
            'remote_id' => 'required',
        ]);

        $category = new Category();
        $category->name = $request->get('name');
        $category->remote_id = $request->get('remote_id');
        $category->remote_data = $request->get('remote_data');
        $category->icon = $request->get('icon');
        $category->is_popular = $request->get('is_popular') ? true : false;
        $category->is_show_in_menu = $request->get('is_show_in_menu') ? true : false;
        $category->is_for_parse = $request->get('is_for_parse') ? true : false;
        $category->save();

        $parent = Category::find($request->get('parent_id'));
        if ($parent) {
            $parent->prependNode($category);
        }

        session()->flash('success', 'Категория создана');

        return redirect()->route('backend.categories.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = Category::findOrFail($id);

        $categories = [
            '' => 'Корневая категория',
        ];
        foreach (Category::withDepth()->get()->toFlatTree() as $category) {
            $categories[$category->id] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', $category->depth) . $category->name;
        }

        return view('backend.categories.form', ['model' => $model, 'categories' => $categories, 'action' => 'edit']);
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
            'remote_id' => 'required',
        ]);

        $category = Category::findOrFail($id);
        $category->name = $request->get('name');
        $category->remote_id = $request->get('remote_id');
        $category->remote_data = $request->get('remote_data');
        $category->icon = $request->get('icon');
        $category->is_popular = $request->get('is_popular') ? true : false;
        $category->is_show_in_menu = $request->get('is_show_in_menu') ? true : false;
        $category->is_for_parse = $request->get('is_for_parse') ? true : false;
        $category->save();

        if ($request->get('parent_id') && $category != $request->get('parent_id')) {
            $parent = Category::find($request->get('parent_id'));
            if ($parent) {
                $parent->prependNode($category);
            }
        } else {
            $category->saveAsRoot();
        }

        session()->flash('success', 'Категория создана');

        return redirect()->route('backend.categories.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = Category::findOrFail($id);

        $model->delete();

        session()->flash('success', 'Категория удалена');

        return redirect()->route('backend.categories.index');
    }
}
