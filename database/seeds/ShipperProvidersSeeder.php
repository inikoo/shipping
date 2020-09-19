<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Wed, 02 Sep 2020 18:48:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

use App\Models\Providers\ApcGb;
use App\Models\Providers\DpdGb;
use App\Models\Providers\DpdSk;
use App\Models\Providers\GlsEs;
use App\Models\Providers\GlsSk;
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
            $shipper_provider       = new Postmen;
            $shipper_provider->slug = 'v3';
            $shipper_provider->save();
        }catch (Exception $e){
            print "Postmen Model v1 already in the database\n";
        }

        try{
            $shipper_provider       = new DpdSk;
            $shipper_provider->slug = 'v2-json';
            $shipper_provider->save();
        }catch (Exception $e){
            print "DPD SK Model v2-json already in the database\n";
        }

        try{
            $shipper_provider       = new GlsSk;
            $shipper_provider->slug = 'MyGLS-v1';
            $shipper_provider->save();
        }catch (Exception $e){
            print "GLS SK Model MyGLS-v1 already in the database\n";
        }

        try{
            $shipper_provider       = new ApcGb;
            $shipper_provider->slug = 'v3';
            $shipper_provider->save();
        }catch (Exception $e){
            print "APC GB already in the database\n";
        }

        try{
            $shipper_provider       = new DpdGb;
            $shipper_provider->slug = 'v3';
            $shipper_provider->save();
        }catch (Exception $e){
            print "DPD GB already in the database\n";
        }

        try{
            $shipper_provider       = new GlsEs;
            $shipper_provider->slug = 'v3';
            $shipper_provider->save();
        }catch (Exception $e){
            print "GLS ES already in the database\n";
        }


    }
}
