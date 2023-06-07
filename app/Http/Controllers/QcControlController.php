<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Station;
use Illuminate\Http\Request;

class QcControlController extends Controller
{
    public function index(Request $request)
    {
        $qc_stations = Station::where('type', 'Q')
            ->get()
            ->pluck('id');

        $totals = Batch::with('section', 'station')
            ->searchStatus('active')
            ->whereIn('station_id', $qc_stations)
            ->whereHas('store', function ($q) {
                $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
            })
            ->groupBy('station_id', 'section_id')
            ->orderBy('section_id')
            ->selectRaw('section_id, station_id, COUNT(*) as count')
            ->get();

        return response()->json($totals, 200);
    }
}
