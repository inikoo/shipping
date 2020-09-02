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
 * @property string $name
 * @property string $code
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


    protected $fillable = [
        'name',
        'code'
    ];

    public function shippers() {
        return $this->hasMany('App\Models\Shipper');
    }

    public function update_number_shippers() {

        $users = Country::withCount(['shippers'])->get();
        dd($users);

    }


}
