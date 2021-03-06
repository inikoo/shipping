<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 09 Sep 2020 17:17:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;

use App\Models\ShipperAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Class ShipperProvider
 *
 * @property array               $credentials_rules
 * @property \App\Models\Shipper $shipper
 * @property array               $credentials
 * @property mixed               $errors
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @package App\Models\Providers
 */
class ShipperProvider extends Model {


    protected string $api_url = '';


    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    protected $fillable = [
        'slug',
    ];


    public function shipper() {
        return $this->morphOne('App\Models\Shipper', 'provider');
    }

    public function createShipperAccount(Request $request) {


        $credentials_validator = Validator::make(
            $request->all(), $this->credentials_rules
        );

        if ($credentials_validator->fails()) {
            $this->errors = $credentials_validator->errors();

            return false;

        }

        $credentials = [];
        foreach ($this->credentials_rules as $credential_field => $foo) {
            $credentials[$credential_field] = $request->get($credential_field);
        }
        $credentials = array_filter($credentials);


        $tenant = (new Tenant)->where('slug', $request->get('tenant'))->first();


        $shipperAccount              = new ShipperAccount;
        $shipperAccount->slug        = $request->get('shipper');
        $shipperAccount->label       = $request->get('label');
        $shipperAccount->shipper_id  = $this->shipper->id;
        $shipperAccount->tenant_id   = $tenant->id;
        $shipperAccount->credentials = $credentials;
        $shipperAccount->save();

        return $shipperAccount;


    }

    public function callApi($url, $headers, $params, $method = 'POST', $result_encoding = 'json') {

        $curl = curl_init();


        curl_setopt_array(
            $curl, array(
                     CURLOPT_URL            => $url,
                     CURLOPT_RETURNTRANSFER => true,
                     CURLOPT_ENCODING       => "",
                     CURLOPT_MAXREDIRS      => 10,
                     CURLOPT_TIMEOUT        => 0,
                     CURLOPT_FOLLOWLOCATION => true,
                     CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                     CURLOPT_CUSTOMREQUEST  => $method,
                     CURLOPT_POSTFIELDS     => $params,
                     CURLOPT_HTTPHEADER     => $headers,
                 )
        );


        $raw_response = curl_exec($curl);

        if ($raw_response == 'Unauthorized') {
            $response['errors'][] = ['fail' => 'Unauthorized'];
            $response['status']   = 530;

            return $response;
        }


        if ($result_encoding == 'xml') {
            $data = json_decode(json_encode(simplexml_load_string($raw_response)), true);
        } elseif ($result_encoding == 'json') {
            $data = json_decode($raw_response, true);
        } else {
            $data = $raw_response;
        }


        $response = [
            'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'data'   => $data
        ];

        if ($raw_response === false) {
            $response['errors'][] = ['curl_fail' => curl_error($curl).' ('.curl_errno($curl).')'];
            $response['status']   = 530;
            curl_close($curl);

            return $response;
        }
        curl_close($curl);

        if ($data == null) {
            $response['errors'][] = ['fail' => 'The API server returned an empty, unknown, or unexplained response'];
            $response['status']   = 530;
        }

        return $response;
    }


    /**
     * @param \Illuminate\Http\Request   $request
     * @param \App\Models\ShipperAccount $shipperAccount
     *
     * @return array|void
     */
    public function getShipmentParameters(Request $request, ShipperAccount $shipperAccount) {


        $parcels          = json_decode($request->get('parcels'), true);
        $shipTo           = json_decode($request->get('ship_to'), true);
        $pickUp           = json_decode($request->get('pick_up'), true);
        $cash_on_delivery = json_decode($request->get('cod', '{}'), true);


        return $this->prepareShipment(
            $shipperAccount, $request, $pickUp, $shipTo, $parcels, $cash_on_delivery

        );


    }

    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcels, $cash_on_delivery) {
        //
    }

    function getLabel($labelID, $shipperAccount, $output) {
        //
    }

    function getServices($request, $shipperAccount) {
        //
    }


    function track($shipment) {
        if (!$shipment->tracking) {
            return false;
        }

        return false;
    }

}
