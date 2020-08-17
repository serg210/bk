<?php

namespace App\Http\Controllers\Frontend;

use App\Models\News;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MainController extends Controller
{

    public function contacts(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        if ($request->isMethod('post')) {
            session()->flash('success', 'Ваше сообщение успешно отправлено');
        }

        return view('pages.contacts');
    }

    public function news(Request $request)
    {
        $pager = News::orderBy('created_at', 'desc')->whereNotNull('created_at')->paginate();

        return view('frontend.news.list', ['pager' => $pager]);
    }

    public function news_show($id, $slug = null)
    {
        $news = News::findOrFail($id);

        return view('frontend.news.show', ['news' => $news]);
    }

}
