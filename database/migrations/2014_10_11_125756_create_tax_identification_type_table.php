<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTaxIdentificationTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iti_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('codigo_afip');
            $table->integer('tax_type_id')->unsigned()->index();
            $table->foreign('tax_type_id')->references('id')->on('tax_types');
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
        Schema::dropIfExists('iti_types');
    }
}
