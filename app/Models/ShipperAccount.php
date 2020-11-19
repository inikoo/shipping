<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 18:22:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;


/**
 * Class ShipperAccount
 *
 * @property integer $id
 * @property string  $slug
 * @property string  $label
 * @property integer $shipper_id
 * @property integer $tenant_id
 * @property array   $credentials
 * @property array   $data
 * @property object  $shipper
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ShipperAccount extends Model {

    protected $casts = [
        'data'        => 'array',
        'credentials' => 'array',
    ];

    protected $attributes = [
        'data'        => '{}',
        'credentials' => '{}'
    ];


    protected $fillable = [
        'slug',
        'name'
    ];

    public function shipper() {
        return $this->belongsTo('App\Models\Shipper');
    }

    public function tenant() {
        return $this->belongsTo('App\Models\Tenant');
    }

    public function shipments() {
        return $this->hasMany('App\Models\Shipment');
    }

    public function createLabel($request) {

        $shipmentData = [];
        if (Arr::get($this->data, 'debug') == 'Yes') {
            $shipmentData = [
                'debug' => [
                    'original_request' => $request->all()
                ]
            ];
        }

        $shipmentData['callback_url'] = $request->get('callback_url');

        $shipment = new Shipment(
            [
                'reference' => $request->get('reference'),
                'data'      => $shipmentData

            ]
        );
        $this->shipments()->save($shipment);

        return $this->shipper->provider->createLabel($shipment, $request, $this);
    }

    public function getLabel($labelID, $output = '') {
        return $this->shipper->provider->getLabel($labelID, $this, $output);
    }

    public function getServices($request) {
        return $this->shipper->provider->getServices($request, $this);
    }

    public function track($shipment) {
        return $this->shipper->provider->track($shipment);
    }


}
