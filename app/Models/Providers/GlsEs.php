<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 19 Sep 2020 13:21:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Shipment;
use App\Models\ShipperAccount;
use Illuminate\Http\Request;

/**
 * Class GlsEs
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class GlsEs extends ShipperProvider {

    protected $table = 'gls_es_shipper_providers';

    protected string $api_url = "";

    protected $credentials_rules = [


    ];

    public function createLabel(Shipment $shipment,Request $request, ShipperAccount $shipperAccount) {


    }


    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


    }


}
