<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 24 Sep 2020 12:11:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Shipment;
use Illuminate\Console\Command;

class UpdateEvents extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update tracking events';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {

        $shipments = Shipment::all();
        $shipments->each(
            function ($shipment) {
                if($shipment->min_state<500){
                    $shipment->track();
                }
            }

        );
        return 0;
    }


}


