<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 02:27:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        print __DIR__;

        $file = file_get_contents(__DIR__."/../../assets/country-by-abbreviation.json");

        foreach (json_decode($file, true) as $key => $value) {
            $country=new Country;
            $country->name=$value['country'];
            $country->code=$value['abbreviation'];
            $country->save();
        }
    }
}
