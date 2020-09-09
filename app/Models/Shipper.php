<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 03:17:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class Shipper
 *
 * @property integer $id
 * @property string $slug
 * @property string $name
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Shipper extends Model {



    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    protected $fillable = [
        'slug',
        'name'
    ];



    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    public function provider()
    {
        return $this->morphTo(__FUNCTION__,'provider_type','provider_id');
    }


    protected static function booted() {
        static::created(
            function ($shipper) {

                $shipper->country->shippers_count=$shipper->country->shippers()->count();
                $shipper->country->save();


            }
        );
    }

}
