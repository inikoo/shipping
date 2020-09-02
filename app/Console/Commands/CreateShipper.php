<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 12:02:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\PostmenShipperProvider;
use App\Models\Shipper;
use Illuminate\Console\Command;

class CreateShipper extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:shipper {country_code} {slug} {name} {provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create country shipper';

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


        /**
         * @var $shipper Shipper
         */
        $shipper = new Shipper(
            [
                'slug' => $this->argument('slug'),
                'name' => $this->argument('name'),
            ]
        );

        $country = (new Country)->where('code', $this->argument('country_code'))->first();

        $shipper = $country->shippers()->save($shipper);


        switch ($this->argument('provider')) {
            default:
                //$shipper_provider = PostmenShipperProvider::where('slug', 'v1')->first();
                $shipper_provider = PostmenShipperProvider::find(1);

        }




        $shipper->provider()->associate($shipper_provider)->save();


        return 0;


    }


}


