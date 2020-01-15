<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('itin_type_id')->unsigned()->nullable(); // Tipo de numero de documento
            $table->foreign('itin_type_id')->references('id')->on('itin_types');
            $table->integer('iti_type_id')->unsigned()->nullable();  // Tipo de tributo
            $table->foreign('iti_type_id')->references('id')->on('iti_types');
            $table->bigInteger('itin')->index()->nullable(); // Numero de documento
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('user');
            $table->rememberToken();
            $table->string('facebook_id')->nullable();
            $table->string('google_id')->nullable();
            $table->string('github_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('website')->nullable();
            $table->boolean('enable_portal')->nullable();
            $table->integer('currency_id')->unsigned()->nullable();
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->integer('company_id')->unsigned()->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
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
        Schema::dropIfExists('users');
    }
}
