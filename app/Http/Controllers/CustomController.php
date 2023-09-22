<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Market\Dropship;

class CustomController extends Controller
{
    public function shipStation(Request $request)
    {
        if ($request->get("orderDateStart")) {
            $created_at_min = $request->get("orderDateStart") . "T00:00:01.000Z";
        } else {
            $created_at_min =   date("Y-m-d") . " T00:00:01.000Z";
        }

        if ($request->get("orderDateEnd")) {
            $created_at_max = $request->get("orderDateEnd") . " T23:59:59.999Z";
        } else {
            $created_at_max = date("Y-m-d") . " T23:59:59.999Z"; // 2020-03-01
        }

        $path = Dropship::getDropShipOrdersDyDate($created_at_min, $created_at_max);

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
