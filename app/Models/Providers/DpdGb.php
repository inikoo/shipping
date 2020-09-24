<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 19 Sep 2020 13:23:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\ShipperAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;


/**
 * Class DpdGb
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DpdGb extends Shipper_Provider {

    protected $table = 'dpd_gb_shipper_providers';

    protected string $api_url = "https://api.dpd.co.uk/";

    protected $credentials_rules = [
        'username'       => ['required',],
        'password'       => ['required'],
        'account_number' => ['required'],
    ];

    public function createLabel(Request $request, ShipperAccount $shipperAccount) {


        if(Arr::get($shipperAccount->data,'geoSession')==''){
            $this->login($shipperAccount);
        }

        $headers = [
            "GeoSession: ".Arr::get($shipperAccount->data,'geoSession'),
            "Content-Type: application/json",
            "Accept: application/json",
            'GeoClient: account/'.$shipperAccount->credentials['account_number']
        ];




        $params = array(

        );


        $apiResponse = $this->call_api(
            $this->api_url.'shipping/shipment', $headers, $params
        );


    }


    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


    }

    function login($shipperAccount) {

        $headers = [
            "Authorization: Basic ".base64_encode($shipperAccount->credentials['username'].':'.$shipperAccount->credentials['password']),
            "Content-Type: application/json",
            "Accept: application/json",
            'GeoClient: account/'.$shipperAccount->credentials['account_number']
        ];


        $params = [];


        $apiResponse = $this->call_api(
            $this->api_url.'user?action=login', $headers, $params
        );

        if($apiResponse['status']==200 and !empty($apiResponse['data']['data']['geoSession'])){
            $shippingAccountData=$shipperAccount->data;
            $shippingAccountData['geoSession']=$apiResponse['data']['data']['geoSession'];
            $shippingAccountData['geoSessionDate']=gmdate('Y-m-d H:i:s');
            $shipperAccount->data=$shippingAccountData;
            $shipperAccount->save();

        }



    }


}
