<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 17:07:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use App\Models\ShipperAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


/**
 * Class DpdSk
 *
 * @property string $slug
 * @property object $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DpdSk extends Model {


    protected $table = 'dpd_sk_shipper_providers';

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

        $credentials_rules = [
            'apiKey'   => ['required'],
            'username' => [
                'required',
                'email'
            ],
            'delisId'  => ['required'],
            'pickupID' => ['required'],
            'bankID'   => [],

        ];

        $credentials_validator = Validator::make(
            $request->all(), $credentials_rules
        );

        if ($credentials_validator->fails()) {
            return response()->json(['errors' => $credentials_validator->errors()]);
        }

        $credentials = [];
        foreach ($credentials_rules as $credential_field => $foo) {
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


}
