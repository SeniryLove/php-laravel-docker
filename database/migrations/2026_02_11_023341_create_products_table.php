<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
             $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2)->unsigned();
            $table->unsignedInteger('stock')->default(0);

            $table->text('image_url')->nullable();

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('is_active')->default(true);

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
        Schema::dropIfExists('products');
    }
}
