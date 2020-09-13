<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 18:01:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Country;
use App\Models\ShipperAccount;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;


/**
 * Class Postmen
 *
 * @property array $data
 * @property string $slug
 * @property mixed  errors
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Postmen extends Shipper_Provider {

    protected $table = 'postmen_shipper_providers';


    public function createShipperAccount(request $request) {



        $url = env('POSTMEN_API_URL', 'https://production-api.postmen.com/v3').'shipper-accounts';


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

        $tenant_address = $tenant->data['address'];
        $country        = (new Country)->where('code', $tenant_address['country_code'])->first();


        $address = [
            'country'      => $country->code_iso3,
            'street1'      => Arr::get($tenant_address, 'address_line_1'),
            'street2'      => Arr::get($tenant_address, 'address_line_2'),
            'city'         => Arr::get($tenant_address, 'locality'),
            'postal_code'  => Arr::get($tenant_address, 'postal_code'),
            'email'        => Arr::get($tenant->data, 'email'),
            'phone'        => Arr::get($tenant->data, 'phone'),
            'contact_name' => Arr::get($tenant->data, 'contact'),
            'company_name' => Arr::get($tenant->data, 'organization'),

        ];
        $address = array_filter($address);


        $params = [
            'slug'        => $request->get('shipper'),
            'description' => $request->get('label'),
            'address'     => $address,
            'timezone'    => 'UTC',
            'credentials' => $credentials
        ];


        $headers = array(
            "content-type: application/json",
            "postmen-api-key: ".$this->data['api_key']
        );



        $response = $this->call_api($url, $headers, $params);

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


}
