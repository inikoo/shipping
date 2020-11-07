<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 24 Sep 2020 12:11:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Shipment;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UpgradingShipment extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upgrading:shipment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh login session fro DPD GB shipping accounts';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {

        $shipments = Shipment::all();

        $shipments->each(
            function ($shipment) {



                $shipment->boxes= count(Arr::get($shipment->data, 'debug.request.Packages', []));

                if($shipment->boxes==0){

                    $tmp=Arr::get($shipment->data, 'debug.original_request.parcels', false);
                    if($tmp){
                        $shipment->boxes= count(json_decode($tmp));

                    }
                }

                if($shipment->data==[]){
                    $shipment->boxes=1;
                }

                if($shipment->boxes==0){

                    dd($shipment->data);
                }


                $tracking = null;
                switch ($shipment->shipperAccount->label) {
                    case 'APC':
                        $tracking = Arr::get($shipment->data, 'debug.response.Orders.Order.WayBill', null);
                        break;
                }
                $shipment->tracking = $tracking;
                $shipment->save();
            }

        );


        return 0;


    }


}

