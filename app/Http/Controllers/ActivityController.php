<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityCollection;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //Activities instance
        $activities = Activity::with('causer');

        //Condition to filter
        if ($request->self)
            $activities = $activities->where('causer_id', $request->model_id);
        else
            $activities = $activities->where('log_name', $request->log_name)
                ->where('subject_id', $request->model_id);

        //Make the collection
        $activities = $activities->latest()->paginate($request->get('rows', 20));

        return ActivityCollection::collection($activities);
    }

    /**
     * Get the models for correspondant logs
     *
     * @param mixed $logName
     * @return string[]|string
     */
    public function getModel($logName)
    {
        switch ($logName) {
            case 'users':
                $models = [
                    "App\Models\User",
                    "App\Models\Employee",
                ];
                break;
                $models = [
                    "App\Models\MachineModel",
                ];
                break;

            case 'designations':
                $models = [
                    "App\Models\Designation",
                ];
                break;


                $models = [
                    "App\Models\DeliveryNote",
                ];
                break;

            default:
                $models = [];
                break;
        }

        return $models;
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Activity  $activity
     * @return \Illuminate\Http\Response
     */
    public function show(Activity $activity)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Activity  $activity
     * @return \Illuminate\Http\Response
     */
    public function edit(Activity $activity)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Activity  $activity
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Activity $activity)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Activity  $activity
     * @return \Illuminate\Http\Response
     */
    public function destroy(Activity $activity)
    {
        //
    }
}
