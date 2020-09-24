<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 24 Sep 2020 12:11:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Providers\DpdGb;
use Illuminate\Console\Command;

class PingDpdGb extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ping:dpd_gb';

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


        $provider= (new DpdGb)->where('slug', 'v3')->first();
        foreach($provider->shipper->shipperAccounts as $shipperAccount){
            $provider->login($shipperAccount);
        }


        return 0;


    }


}


