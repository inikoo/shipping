<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 12:02:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Providers\ApcGb;
use App\Models\Providers\DpdGb;
use App\Models\Providers\DpdSk;
use App\Models\Providers\GlsEs;
use App\Models\Providers\GlsSk;
use App\Models\Providers\Postmen;
use App\Models\Providers\WhistlGb;
use App\Models\Shipper;
use Exception;
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
     * @throws \Exception
     */
    public function handle() {


        $_shipper = new Shipper(
            [
                'slug' => $this->argument('slug'),
                'name' => $this->argument('name'),
            ]
        );

        $country = (new Country)->where('code', $this->argument('country_code'))->first();

        /**
         * @var $shipper Shipper
         */
        $shipper = $country->shippers()->save($_shipper);


        switch ($this->argument('provider')) {
            case 'dpd-sk':
                $shipper_provider = (new DpdSk)->where('slug', 'v2-json')->first();
                break;
            case 'dpd-gb':
                $shipper_provider = (new DpdGb)->where('slug', 'v3')->first();
                break;
            case 'gls-sk':
                $shipper_provider = (new GlsSk)->where('slug', 'MyGLS-v1')->first();
                break;
            case 'gls-es':
                $shipper_provider = (new GlsEs)->where('slug', 'v3')->first();
                break;
            case 'apc-gb':
                $shipper_provider = (new ApcGb)->where('slug', 'v3')->first();
                break;
            case 'whistl-gb':
                $shipper_provider = (new WhistlGb)->where('slug', 'v1')->first();
                break;
            case 'postmen-devel':
                $shipper_provider = (new Postmen)->where('slug', 'v3-devel')->first();
                break;
            case 'postmen':
                $shipper_provider = (new Postmen)->where('slug', 'v3')->first();
                break;
            default:
                throw new Exception('No provider found');

        }


        $shipper->provider()->associate($shipper_provider)->save();


        return 0;


    }


}


