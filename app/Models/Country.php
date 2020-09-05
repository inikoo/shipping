<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 03:17:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class Country
 *
 * @property string  $name
 * @property string  $code
 * @property string  $code_iso3
 * @property integer $code_iso_numeric
 * @property string  $continent
 * @property string  $capital
 * @property string  $timezone
 * @property string  $phone_code
 * @property integer $geoname_id
 * @property array   $data
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Country extends Model {

    protected $table = 'countries';


    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    //protected $fillable = [
    //    'name',
    //    'code'
    //];

    public function shippers() {
        return $this->hasMany('App\Models\Shipper');
    }


}
