<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Fri, 04 Sep 2020 18:22:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class ShipperAccount
 *
 * @property string $slug
 * @property string $label
 * @property integer $shipper_id
 * @property integer $tenant_id
 * @property array $credentials
 * @property array $data
 * @property object $shipper

 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ShipperAccount extends Model {



    protected $casts = [
        'data' => 'array',
        'credentials' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
        'credentials'=> '{}'
    ];


    protected $fillable = [
        'slug',
        'name'
    ];


    public function shipper()
    {
        return $this->belongsTo('App\Models\Shipper');
    }

    public function tenant()
    {
        return $this->belongsTo('App\Models\Tenant');
    }


    public function createLabel($request){

        return $this->shipper->provider->createLabel($request,$this);

    }

    public function pdf_labels() {
        return $this->hasMany('App\Models\PdfLabel');
    }



}
