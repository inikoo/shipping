<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 31 Oct 2020 15:31:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterPdfLabelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pdf_labels', function (Blueprint $table) {
            $table->dropColumn('shipper_account_id');

            $table->integer('shipment_id')->index()->nullable()->after('id');
            $table->foreign('shipment_id')->references('id')->on('shipments');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pdf_labels', function (Blueprint $table) {
            $table->dropColumn('shipment_id');
            $table->integer('shipper_account_id')->nullable()->index();
        });


    }
}
