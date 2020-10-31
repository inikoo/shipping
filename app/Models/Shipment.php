<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 31 Oct 2020 15:23:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class Shipment
 *
 * @property integer $id
 * @property integer $shipper_account_id
 * @property string  $status
 * @property array   $data
 * @property array   $request
 * @property object  $response
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Shipment extends Model {

    protected $casts = [
        'data'     => 'array',
        'request'  => 'array',
        'response' => 'array',
    ];

    protected $attributes = [
        'data'     => '{}',
        'request'  => '{}',
        'response' => '{}'
    ];

    protected $guarded = [];


    public function shipper_account() {
        return $this->belongsTo('App\Models\ShipperAccount');
    }


    public function pdf_label() {
        return $this->hasOne('App\Models\PdfLabel');
    }


}
