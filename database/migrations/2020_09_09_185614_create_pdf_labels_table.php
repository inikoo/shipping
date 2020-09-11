<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePdfLabelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pdf_labels', function (Blueprint $table) {
            $table->id();
            $table->integer('shipper_account_id')->index();
            $table->foreign('shipper_account_id')->references('id')->on('shipper_accounts');
            $table->string('checksum')->index();
            $table->longText('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pdf_labels');
    }
}
