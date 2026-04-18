<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('order_id')
                  ->constrained()
                  ->onDelete('cascade');
        
            $table->string('method'); // ecpay / linepay
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
        
            $table->enum('status', [
                'pending',
                'paid',
                'failed',
                'refunded'
            ])->default('pending');
        
            $table->json('raw_response')->nullable(); // 儲存第三方回傳完整資料
        
            $table->timestamp('paid_at')->nullable();
        
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
        Schema::dropIfExists('payments');
    }
}
