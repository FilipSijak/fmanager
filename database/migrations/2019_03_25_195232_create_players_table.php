<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\GameEngine\Player\PlayerConfiguration\PlayerFieldsConfig;

class CreatePlayersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {

            $allPlayerFields = array_merge(
                PlayerFieldsConfig::TEHNICAL_FIELDS,
                PlayerFieldsConfig::MENTAL_FIELDS,
                PlayerFieldsConfig::PHYSICAL_FILDS
            );

            $table->increments('id');
            $table->timestamps();
            $table->string('first_name', 30);
            $table->string('last_name', 30);
            $table->integer('country_id')->nullable();
            $table->date('dob')->nullable();
            $table->integer('technical')->nullable();
            $table->integer('mental')->nullable();
            $table->integer('physical')->nullable();

            foreach ($allPlayerFields as $field) {
                $table->integer($field);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('players');
    }
}
