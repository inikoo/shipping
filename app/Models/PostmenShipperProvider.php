<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 03:17:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class PostmenShipperProvider
 *
 * @property string $slug
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PostmenShipperProvider extends Model {



    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    protected $fillable = [
        'slug',
    ];


    public function shipper()
    {
        return $this->morphOne('App\Models\Shipper', 'provider');
    }



}
