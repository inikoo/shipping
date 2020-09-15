<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Tue, 15 Sep 2020 12:45:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\ShipperAccount;
use Illuminate\Http\Request;


/**
 * Class ApcGb
 *
 * @property string $slug
 * @property object $shipper
 * @property array  $credentials
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ApcGb extends Shipper_Provider {


    protected $table = 'apc_gb_shipper_providers';

    protected $credentials_rules = [
        'email'   => ['required','email'],
        'password'=> ['required'],

    ];


    function __construct(array $attributes = []) {
        parent::__construct($attributes);

        $this->api_url =  env('APC_API_URL',"https://apc.hypaship.com/api/3.0/");

    }

    public function createLabel(Request $request, ShipperAccount $shipperAccount) {

    }

    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {

    }

}

