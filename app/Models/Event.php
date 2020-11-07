<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 07 Nov 2020 15:16:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class Event
 *
 * @property integer $id
 * @property integer $shipment_id
 * @property string  $box
 * @property string  $type
 * @property string  $code
 * @property array   $data
 * @property Shipment $shipment
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Event extends Model {

    protected $casts = [
        'data'     => 'array'
    ];

    protected $attributes = [
        'data'     => '{}'
    ];

    protected $guarded = [];


    public function shipment() {
        return $this->belongsTo('App\Models\Shipment');
    }




}
