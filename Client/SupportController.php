<?php

namespace App\Http\Controllers\Client;

use App\Models\SupportMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class SupportController extends Controller
{

    public function index()
    {
        $client = auth('client')->user();

        $messages = SupportMessage::where('client_id', $client->id)->whereIn('from', ['client', 'partner'])->orderBy('created_at', 'desc')->get();

        return view('client.support.index', ['messages' => $messages]);
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'message' => 'required'
        ]);

        $client = auth('client')->user();
        // save file if exist
        $filePath = NULL;
        if ($request->hasFile('file') && $request->file('file')->isValid()){
            $filePath = $request->file->store('support');
        }

        $message = new SupportMessage();
        $message->partner_id = $client->partner_id;
        $message->client_id = $client->id;
        $message->message = $request->get('message');
        $message->file = $filePath;
        $message->from = 'client';
        $message->is_read_by_client = true;
        $message->save();

        $client->last_support_message_at = new Carbon();
        $client->save();

        session()->flash('success', 'Ваше сообщение отправление. В ближайшее время с вами свяжется оператор.');

        return redirect()->route('client.support.index');
    }

    public function read($id)
    {
        $message = SupportMessage::where([
            'id' => $id,
            'client_id' => auth('client')->user()->id,
        ])->firstOrFail();

        $message->is_read_by_client = true;
        $message->save();

        return redirect()->route('client.support.index');
    }

    public function downloadFile($file){

        $file = 'support/'.$file;
        $client = auth('client')->user();
        $message = SupportMessage::where('client_id', $client->id)->where('file', $file)->orderBy('created_at', 'desc')->first();
        if($message && Storage::exists($file)){
            return Storage::download($file);
        } else {
            return abort(404);
        }
    }

}
