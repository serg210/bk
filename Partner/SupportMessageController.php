<?php

namespace App\Http\Controllers\Partner;

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
        $partner = auth('user')->user();

        $clients = ['' => 'Выберите из списка'];
        foreach (Client::orderBy('name', 'asc')->where('partner_id', $partner->id)->whereHas('support_messages')->get() as $client) {
            $clients[$client->id] = $client->name . ' [ ID: ' . $client->id . ']';
        }

        $period = [
            Carbon::now()->subMonth(1)->toDateTimeString(),
            Carbon::now()->toDateTimeString(),
        ];

        $filter = [
            'client_id' => $request->input('filter.client_id'),
            'period' => $request->input('filter.period', implode(' - ', $period)),
        ];


        $query = SupportMessage::with(['client'])->where('partner_id', $partner->id)->whereIn('from', ['client', 'partner'])->orderBy('is_read_by_partner', 'asc');

        //->where('from', 'client')

        if ($filter['client_id']) {
            $query->where('client_id', $filter['client_id']);
        }

        if ($filter['period']) {
            $arPeriod = explode(' - ', $filter['period']);
            $arPeriod[0] = Carbon::parse($arPeriod[0])->format('Y-m-d');
            $arPeriod[1] = Carbon::parse($arPeriod[1])->addDay()->format('Y-m-d');
            $query->whereBetween('created_at', $arPeriod);

        }

        $pager = $query->paginate();

        return view('partner.support_messages.index', ['pager' => $pager, 'filter' => $filter, 'clients' => $clients]);
    }

    public function create(Request $request)
    {
        $partner = auth('user')->user();

        if($request->id){
            $filter = [
                'client_id' => $request->id,
            ];
        }else{
            $filter = [
                'client_id' => $request->input('filter.client_id'),
            ];
        }

        $clients = [];
        foreach (Client::orderBy('email', 'asc')->where('partner_id', $partner->id)->get() as $client) {
            $clients[$client->id] = '[ ID: ' . $client->id . ' ] ' . $client->name . ' [ ' . $client->email . ' ]';
        }

        if ($request->isMethod('post')) {
            $this->validate($request, [
                'client_id' => 'required',
                'message' => 'required',
//                'title' => 'required',
            ]);

            $some_clients = $request->input('client_id');
            $some_clients = implode(',', $some_clients);
            $some_clients = explode(",", $some_clients);

            foreach ($some_clients as $clients) {
                if (!isset($clients)) {
                    session()->falsh('danger', 'Oops!');

                    return redirect()->route('partner.support_messages.index');
                }

                $support_message = new SupportMessage();

                // save file if exist
                if ($request->hasFile('file') && $request->file('file')->isValid()){
                    $support_message->file = $request->file->store('support');
                }

                $support_message->partner_id = $partner->id;
                $support_message->client_id = $clients;
                $support_message->from = 'partner';
                $support_message->is_read_by_partner = true;
                $support_message->message = $request->get('message');
                $support_message->save();
            }

            session()->flash('success', 'Сообщение отправлено');

            return redirect()->route('partner.support_messages.index');
        }

        return view('partner.support_messages.create', ['clients' => $clients, 'filter' => $filter]);
    }

    public function show(Request $request, $id)
    {
        $partner = auth('user')->user();

        if ($request->isMethod('post')) {
            $this->validate($request, ['message' => 'required']);

            $message = new SupportMessage();

            // save file if exist
            if ($request->hasFile('file') && $request->file('file')->isValid()){
                $message->file = $request->file->store('support');
            }

            $message->partner_id = $partner->id;
            $message->client_id = $id;
            $message->message = $request->get('message');
            $message->from = 'partner';
            $message->is_read_by_partner = true;
            $message->save();

            Client::where('id', $id)->update(['last_support_message_at' => new Carbon()]);
        }

        $messages = SupportMessage::where('client_id', $id)->where('partner_id', $partner->id)->orderBy('created_at', 'asc')->get();

        return view('partner.support_messages.show', ['messages' => $messages, 'id' => $id]);
    }

    public function toggle($id)
    {
        $message = SupportMessage::where(['id' => $id, 'partner_id' => auth('user')->user()->id])->firstOrFail();

        $message->is_read_by_partner = !$message->is_read_by_partner;
        $message->save();

        if(request()->input('only_toggle', false)){
            return response('ok',200);
        }

        return redirect()->route('partner.support_messages.show', ['id' => $message->client_id]);
    }

    public function edit(Request $request, $id)
    {
        $message = SupportMessage::where(['id' => $id, 'partner_id' => auth('user')->user()->id])->firstOrFail();

        if ($request->isMethod('post')) {
            $message->message = $request->get('message');

            // save file if exist
            if ($request->hasFile('file') && $request->file('file')->isValid()){
                $message->file = $request->file->store('support');
            }

            $message->save();

            session()->flash('success', 'Сообщение обновлено');

            return redirect()->route('partner.support_messages.show', ['id' => $message->client_id]);
        }

        return view('partner.support_messages.edit', ['message' => $message]);
    }

    public function delete($id)
    {
        $message = SupportMessage::where(['id' => $id, 'partner_id' => auth('user')->user()->id])->firstOrFail();

        if($message->file && Storage::exist($message->file)){
            Storage::delete($message->file);
        }
        $message->delete();

        session()->flash('success', 'Сообщение удалено');

        return redirect()->route('partner.support_messages.show', ['id' => $id]);
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
