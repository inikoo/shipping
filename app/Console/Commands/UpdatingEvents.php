<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 24 Sep 2020 12:11:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Shipment;
use Illuminate\Console\Command;

class UpdatingEvents extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updating:events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update tracking events';

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

                $shipment->track();

            }

        );


        return 0;


    }


}


