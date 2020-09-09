<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:39:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

namespace App\Models\Providers;


/**
 * Class GlsSk
 *
 * @property string $slug
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class GlsSk extends Shipper_Provider {

    protected $table = 'gls_sk_shipper_providers';

    protected $credentials_rules = [
        'username'      => [
            'required',
            'email'
        ],
        'password'      => ['required'],
        'client_number' => [
            'required',
            'numeric'
        ],

    ];




}
