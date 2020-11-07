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
 * @property string  $reference
 * @property string  $tracking
 * @property string  $error_message
 * @property array   $data
 * @property integer   $boxes
 * @property ShipperAccount $shipperAccount
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Shipment extends Model {

    protected $casts = [
        'data'     => 'array'
    ];

    protected $attributes = [
        'data'     => '{}'
    ];

    protected $guarded = [];


    public function shipperAccount() {
        return $this->belongsTo('App\Models\ShipperAccount');
    }


    public function pdfLabel() {
        return $this->hasOne('App\Models\PdfLabel');
    }

    public function track() {
        $this->shipperAccount->track($this);

    }

}
