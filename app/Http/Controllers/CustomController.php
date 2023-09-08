<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Market\Dropship;

class CustomController extends Controller
{
    public function shipStation()
    {

        $path = Dropship::getDropShipOrders();

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
