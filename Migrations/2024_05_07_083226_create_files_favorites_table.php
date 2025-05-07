<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateFilesFavoritesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('files_favorites', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('IdUser')->default(0);
            $table->string('Type')->default('');
            $table->string('FullPath')->default('');
            $table->string('DisplayName')->default('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('files_favorites');
    }
}
