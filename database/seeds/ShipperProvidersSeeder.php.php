<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:48:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

use App\Models\PostmenShipperProvider;
use Illuminate\Database\Seeder;

class ShipperProvidersSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        try{
            $postmen_shipper_provider       = new PostmenShipperProvider;
            $postmen_shipper_provider->slug = 'v1';
            $postmen_shipper_provider->save();
        }catch (Exception $e){
            print "Postmen Model v1 already in the database\n";
        }



    }
}
