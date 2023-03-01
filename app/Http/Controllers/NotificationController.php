<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationCollection;
use App\Models\DatabaseNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $notifications = DatabaseNotification::where('notifiable_id', auth()->user()->id)->latest()->get();
        $notifications = $notifications->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->data['title'],
                'subtitle' => $item->data['subtitle'],
                'url' => $item->data['url'],
                'datetime' => $item->created_at,
                'isRead' => $item->read_at ? 1 : 0,
                'data' => $item->data['data'],
            ];
        });
        return $notifications;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $notifications = DatabaseNotification::where('notifiable_id', $id)->whereNull('read_at')->latest()->get();
        $notifications = $notifications->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->data['title'],
                'subtitle' => $item->data['subtitle'],
                'url' => $item->data['url'],
                'datetime' => $item->created_at,
                'data' => $item->data['data'],
            ];
        });
        return $notifications;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function notificationRead($id)
    {
        $data = DatabaseNotification::findOrFail($id);
        $data->update([
            'read_at' => Carbon::now(),
        ]);
        return ['data' => $data];
    }

    public function getAll(Request $request)
    {
        $notification = user()->notifications();

        $notification = $notification->paginate($request->get('rows', 10));

        return NotificationCollection::collection($notification);


        // return ["data" =>$notification];
    }
}
