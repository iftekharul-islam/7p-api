<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Market\Dropship;

class pullShipStationOrderByToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pull-ship-station';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        logger('ShipStation Order By Today ' . date("Y-m-d") . ' started');

        $created_at_min =   date("Y-m-d") . " T00:00:01.000Z";
        $created_at_max = date("Y-m-d") . " T23:59:59.999Z"; // 2020-03-01
        $path = Dropship::getDropShipOrdersDyDate($created_at_min, $created_at_max);

        logger('Pulling ShipStation Order By Today completed with csv file link : ', [$path]);
    }
}
