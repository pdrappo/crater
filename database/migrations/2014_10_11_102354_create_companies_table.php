<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('unique_hash')->nullable();
            $table->integer('itin_type_id')->unsigned()->nullable();
            //$table->foreign('itin_type_id')->references('id')->on('itin_types');
            $table->integer('iti_type_id')->unsigned()->nullable();
            //$table->foreign('iti_type_id')->references('id')->on('iti_types');
            $table->bigInteger('itin')->index()->nullable();
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
        Schema::dropIfExists('companies');
    }
}
