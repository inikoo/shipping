<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Thu, 10 Sep 2020 03:05:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class PdfLabel
 *
 * @property integer  $id
 * @property integer $shipper_account_id
 * @property string  $checksum
 * @property string  $data
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PdfLabel extends Model {


    protected $fillable=[
        'checksum','data'
    ];

    public function shipment()
    {
        return $this->belongsTo('App\Models\Shipment');
    }


}
