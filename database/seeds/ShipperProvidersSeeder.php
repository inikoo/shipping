<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:48:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

use App\Models\Providers\DpdSk;
use App\Models\Providers\Postmen;
use Illuminate\Database\Seeder;

class ShipperProvidersSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {

        try{
            $postmen_shipper_provider       = new Postmen;
            $postmen_shipper_provider->slug = 'v3';
            $postmen_shipper_provider->save();
        }catch (Exception $e){
            print "Postmen Model v1 already in the database\n";
        }

        try{
            $postmen_shipper_provider       = new DpdSk;
            $postmen_shipper_provider->slug = 'v2-json';
            $postmen_shipper_provider->save();
        }catch (Exception $e){
            print "DPD SK Model v2-json already in the database\n";
        }



    }
}
