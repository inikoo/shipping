<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 14:59:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


/**
 * Class Tenant
 * @property integer $id
 * @property string $slug
 * @property string $name
 * @property array $data
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Tenant extends Model {



    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
    ];


    protected $fillable = [
        'slug','data'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    protected static function booted() {
        static::created(
            function ($tenant) {

                $tenant->user->tenants_count=$tenant->user->tenants()->count();
                $tenant->user->save();


            }
        );
    }

}
