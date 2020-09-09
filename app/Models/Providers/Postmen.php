<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 18:01:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;


/**
 * Class Postmen
 *
 * @property string $slug
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Postmen extends model {

    protected $table = 'postmen_shipper_providers';





    public function createShipperAccount(request $request) {

        $url = env('postmen_api_url').'shipper-accounts';


        $credentials_rules     = $this->get_credentials_validation($request->get('shipper'));
        $credentials_validator = validator::make($request->all(), $credentials_rules);
        if ($credentials_validator->fails()) {

            return response()->json(['errors' => $credentials_validator->errors()]);
        }

        $credentials = [];
        foreach ($credentials_rules as $credential_field => $foo) {
            $credentials[$credential_field] = $request->get($credential_field);
        }
        $credentials = array_filter($credentials);

        $tenant = (new tenant)->where('slug', $request->get('tenant'))->first();


        $address = array_filter($tenant->data['address']);


        $data = [
            'slug'        => $request->get('shipper'),
            'description' => $request->get('description'),
            'address'     => $address,
            'timezone'    => 'utc',
            'credentials' => $credentials
        ];


        $method  = 'post';
        $headers = array(
            "content-type: application/json",
            "postmen-api-key: ".env('postmen_api_key')
        );

        $body = json_encode($data);

        $curl = curl_init();

        curl_setopt_array(
            $curl, array(
                     CURLOPT_RETURNTRANSFER => true,
                     CURLOPT_URL            => $url,
                     CURLOPT_CUSTOMREQUEST  => $method,
                     CURLOPT_HTTPHEADER     => $headers,
                     CURLOPT_POSTFIELDS     => $body
                 )
        );

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "curl error #:".$err;
        } else {
            return $response;
        }

    }


    public function get_credentials_validation($slug) {
        switch ($slug) {
            case 'dpd':
                return ['slid' => ['required']];
            default:
                return [];
        }
    }

}
