<?php
/*
 * Author: Raul A Perusquía-Flores (raul@aiku.io)
 * Created: Sat, 07 Nov 2020 01:53:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2020. Aiku.io
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RefactoringShippingTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table(
            'shipments', function (Blueprint $table) {
            $table->renameColumn('reference_2', 'tracking');
            $table->renameColumn('reference_3', 'error_message');
            $table->unsignedSmallInteger('boxes')->default(1);

            $table->string('min_state')->nullable()->index();
            $table->string('max_state')->nullable()->index();

        }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table(
            'shipments', function (Blueprint $table) {
            $table->renameColumn('tracking', 'reference_2');
            $table->renameColumn('error_message', 'reference_3');
            $table->dropColumn('state');
        }
        );
    }
}
