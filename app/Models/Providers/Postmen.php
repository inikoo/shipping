<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 18:01:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\Shipment;
use App\Models\ShipperAccount;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;


/**
 * Class Postmen
 *
 * @property array  $data
 * @property string $slug
 * @property mixed  errors
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Postmen extends ShipperProvider {

    protected $table = 'postmen_shipper_providers';
    protected array $headers = [];

    function __construct(array $attributes = []) {
        parent::__construct($attributes);

        $this->api_url = env('POSTMEN_API_URL', 'https://production-api.postmen.com/v3/');

    }

    public function createShipperAccount(request $request) {


        $this->headers = array(
            "content-type: application/json",
            "postmen-api-key: ".$this->data['api_key']
        );

        $credentials_rules     = $this->get_credentials_validation($request->get('shipper'));
        $credentials_validator = validator::make($request->all(), $credentials_rules);
        if ($credentials_validator->fails()) {
            $this->errors = $credentials_validator->errors();
            return false;
        }

        $credentials = [];
        foreach ($credentials_rules as $credential_field => $foo) {
            $credentials[$credential_field] = $request->get($credential_field);
        }
        $credentials = array_filter($credentials);

        $tenant = (new tenant)->where('slug', $request->get('tenant'))->first();

        $params = [
            'slug'        => $request->get('shipper'),
            'description' => $request->get('label'),
            'address'     => $this->get_tenant_address($tenant),
            'timezone'    => 'UTC',
            'credentials' => $credentials
        ];

        $response = $this->callApi($this->api_url.'shipper-accounts', $this->headers, json_encode($params));

        if ($response['status'] != 200) {
            $this->errors = [Arr::get($response, 'errors')];

            return false;
        }

        if ($response['data']['meta']['code'] != 200) {
            $this->errors = [$response['data']];
            return false;
        }

        $shipperAccount             = new ShipperAccount;
        $shipperAccount->slug       = $request->get('shipper');
        $shipperAccount->label      = $request->get('label');
        $shipperAccount->shipper_id = $this->shipper->id;
        $shipperAccount->tenant_id  = $tenant->id;
        $shipperAccount->data       = $response['data']['data'];
        $shipperAccount->save();

        return $shipperAccount;


    }

    public function get_credentials_validation($slug) {
        switch ($slug) {
            case 'dpd':
                return ['slid' => ['required']];

            case 'apc-overnight':
                return [
                    'password'   => ['required'],
                    'user_email' => ['required'],

                ];
            default:
                return [];
        }
    }

    public function createLabel(Shipment $shipment,Request $request, ShipperAccount $shipperAccount) {

        $debug=Arr::get($shipperAccount->data, 'debug') == 'Yes';

        $this->headers = array(
            "content-type: application/json",
            "postmen-api-key: ".$this->data['api_key']
        );

        $params=$this->getShipmentParameters($request, $shipperAccount);

        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.request',$params);
            $shipment->data=$shipmentData;
            $shipment->save();
        }

        $apiResponse = $this->callApi(
            $this->api_url.'labels', $this->headers, json_encode($params)
        );


        if ($debug) {
            $shipmentData=$shipment->data;
            data_fill($shipmentData,'debug.response', $apiResponse['data']);
            $shipment->data=$shipmentData;
        }


        $shipment->status   = 'error';

        $result = [];
        if ($apiResponse['data']['meta']['code'] != 200) {
            $this->errors       = [$apiResponse['data']['meta']];
            $result['errors'][] = [$apiResponse['data']['meta']['code'] => trim($apiResponse['data']['meta']['message'].' '.json_encode($apiResponse['data']['meta']['details']))];
        } else {
            $shipment->status = 'success';

            $result['tracking_number'] = join($apiResponse['data']['data']['tracking_numbers']);
            $result['label_link']  = $apiResponse['data']['data']['files']['label']['url'];
            $result['shipment_id'] = $shipment->id;


        }
        $shipment->save();

        return $result;

    }

    function get_tenant_address($tenant) {

        $tenant_address = $tenant->data['address'];
        $tenant_country = (new Country)->where('code', $tenant_address['country_code'])->first();


        $tenant_address = [
            'country'      => $tenant_country->code_iso3,
            'street1'      => Arr::get($tenant_address, 'address_line_1'),
            'street2'      => Arr::get($tenant_address, 'address_line_2'),
            'city'         => Arr::get($tenant_address, 'locality'),
            'postal_code'  => Arr::get($tenant_address, 'postal_code'),
            'email'        => Arr::get($tenant->data, 'email'),
            'phone'        => Arr::get($tenant->data, 'phone'),
            'contact_name' => Arr::get($tenant->data, 'contact'),
            'company_name' => Arr::get($tenant->data, 'organization'),

        ];

        return array_filter($tenant_address);
    }


    function prepareShipment($shipperAccount, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery) {


        $shipToCountry = (new Country)->where('code', Arr::get($shipTo, 'country_code'))->first();


        $shipTo = [
            'country'      => $shipToCountry->code_iso3,
            'street1'      => Arr::get($shipTo, 'address_line_1'),
            'street2'      => Arr::get($shipTo, 'address_line_2'),
            'city'         => Arr::get($shipTo, 'locality'),
            'postal_code'  => Arr::get($shipTo, 'postal_code'),
            'email'        => Arr::get($shipTo, 'email'),
            'phone'        => Arr::get($shipTo, 'phone'),
            'contact_name' => Arr::get($shipTo, 'contact'),
            'company_name' => Arr::get($shipTo, 'organization'),

        ];

        $shipTo = array_filter($shipTo);


        $parcels    = [];
        $references = [];
        foreach ($parcelsData as $parcelData) {
            $references[] = $request->get('reference');
            $parcels[]    = [
                'box_type'  => 'custom',
                'dimension' => [
                    'width'  => $parcelData['width'],
                    'height' => $parcelData['height'],
                    'depth'  => $parcelData['depth'],
                    'unit'   => 'cm'

                ],
                'weight'    => [
                    'value' => $parcelData['weight'],
                    'unit'  => 'kg'

                ],
                'items'     => [
                    [
                        'description' => $request->get('reference').' items',
                        'quantity'    => 1,
                        'price'       => [
                            'amount'   => 0,
                            'currency' => 'GBP'
                        ],
                        'weight'      => [
                            'value' => $parcelData['weight'],
                            'unit'  => 'kg'

                        ],
                    ]
                ]
            ];
        }

        return array(
            'service_type'          => $request->get('service_type'),
            'shipper_account'       => ['id' => $shipperAccount->data['id']],
            'shipment'              => [
                'ship_from' => $this->get_tenant_address($shipperAccount->tenant),
                'ship_to'   => $shipTo,
                'parcels'   => $parcels
            ],
            'delivery_instructions' => $request->get('note'),

            'references'   => $references,
            'order_number' => $request->get('reference'),

        );

    }


}
