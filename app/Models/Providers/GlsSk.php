<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:39:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


use Illuminate\Database\Eloquent\Model;


/**
 * Class GlsSk
 *
 * @property string $slug
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class GlsSk extends Model {

    protected $table = 'gls_sk_shipper_providers';


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
