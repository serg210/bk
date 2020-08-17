<?php

namespace App\Http\Controllers\Backend;

use App\Models\Client;
use App\Models\SupportMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class SupportMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $partners = ['' => 'Выберите из списка'];
        foreach (User::orderBy('name', 'asc')->get() as $partner) {
            $partners[$partner->id] = $partner->name . ' [ ID: ' . $partner->id . ']';
        }

        $clients = ['' => 'Выберите из списка'];
        foreach (Client::orderBy('name', 'asc')->whereHas('support_messages')->get() as $client) {
            $clients[$client->id] = $client->name . ' [ ID: ' . $client->id . ']';
        }

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'partner_id' => $request->input('filter.partner_id'),
            'client_id' => $request->input('filter.client_id'),
            'period' => $request->input('filter.period', implode(' - ', $period)),
        ];


        $query = SupportMessage::with(['partner', 'client', 'client.support_messages'])->whereIn('from', ['client', 'partner'])->orderBy('is_read_by_partner', 'asc');
        //where('from', 'client')->

        if ($filter['partner_id']) {
            $query->where('partner_id', $filter['partner_id']);
        }

        if ($filter['client_id']) {
            $query->where('client_id', $filter['client_id']);
        }

//        if ($filter['period']) {
//            $query->whereBetween('created_at', explode(' - ', $filter['period']));
//        }
        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $query->whereBetween('created_at', $arPeriod);

        }

        $pager = $query->paginate(25);

        return view('backend.support_messages.index', ['pager' => $pager, 'filter' => $filter, 'partners' => $partners, 'clients' => $clients]);
    }

    public function create(Request $request)
    {
        $clients = ['' => 'Выберите из списка'];
        foreach (Client::orderBy('email', 'asc')->get() as $client) {
            $clients[$client->id] = '[ ID: ' . $client->id . ' ] ' . $client->name . ' [ ' . $client->email . ' ]';
        }

        if ($request->isMethod('post')) {
            $this->validate($request, [
                'client_id' => 'required',
                'message' => 'required',
            ]);

            if (!isset($clients[$request->get('client_id')])) {
                session()->falsh('danger', 'Oops!');

                return redirect()->route('backend.support_messages.index');
            }

            $support_message = new SupportMessage();

            if ($request->hasFile('file') && $request->file('file')->isValid()){
                $support_message->file = $request->file->store('support');
            }

            $support_message->client_id = $request->get('client_id');
            $support_message->from = 'partner';
            $support_message->is_read_by_partner = true;
            $support_message->message = $request->get('message');
            $support_message->save();

            session()->flash('success', 'Сообщение отправлено');

            return redirect()->route('backend.support_messages.index');
        }

        return view('backend.support_messages.create', ['clients' => $clients]);
    }

    public function show(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, ['message' => 'required']);

            $message = new SupportMessage();

            if ($request->hasFile('file') && $request->file('file')->isValid()){
                $message->file = $request->file->store('support');
            }

            $message->partner_id = auth('user')->user()->id;
            $message->client_id = $id;
            $message->message = $request->get('message');
            $message->from = 'partner';
            $message->is_read_by_partner = true;
            $message->save();

            Client::where('id', $id)->update(['last_support_message_at' => new Carbon()]);
        }

        $messages = SupportMessage::where('client_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return view('backend.support_messages.show', ['messages' => $messages, 'id' => $id]);
    }

    public function toggle($id)
    {
        $message = SupportMessage::where(['id' => $id])->firstOrFail();

        $message->is_read_by_partner = !$message->is_read_by_partner;
        $message->save();

        return redirect()->route('backend.support_messages.show', ['id' => $message->client_id]);
    }

    public function edit(Request $request, $id)
    {
        $message = SupportMessage::where(['id' => $id])->firstOrFail();

        if ($request->isMethod('post')) {
            $message->message = $request->get('message');

            if ($request->hasFile('file') && $request->file('file')->isValid()){
                $message->file = $request->file->store('support');
            }

            $message->save();

            session()->flash('success', 'Сообщение обновлено');

            return redirect()->route('backend.support_messages.show', ['id' => $message->client_id]);
        }

        return view('backend.support_messages.edit', ['message' => $message]);
    }

    public function delete($id)
    {
        $message = SupportMessage::where('id', $id)->firstOrFail();

        $client_id = $message->client_id;

        if($message->file && Storage::exist($message->file)){
            Storage::delete($message->file);
        }

        $message->delete();

        session()->flash('success', 'Сообщение удалено');

        return redirect()->route('backend.support_messages.show', ['id' => $client_id]);
    }

    public function downloadFile($file){
        $file = 'support/'.$file;

        if(Storage::exists($file)){
            return Storage::download($file);
        } else {
            return abort(404);
        }
    }

}
