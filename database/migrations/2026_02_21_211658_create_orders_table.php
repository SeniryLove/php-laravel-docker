<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
        
            $table->decimal('total_amount', 10, 2)->default(0);
        
            // 訂單狀態（物流流程）
            $table->enum('status', [
                'pending',
                'processing',
                'paid',
                'shipped',
                'completed',
                'cancelled'
            ])->default('pending');
        
            // 金流相關
            $table->string('payment_method')->nullable(); // ecpay / linepay
            $table->string('payment_status')->default('unpaid'); // unpaid / paid / failed / refunded
            $table->string('transaction_id')->nullable(); // 第三方交易編號
            $table->string('merchant_order_no')->unique()->nullable();
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
        Schema::dropIfExists('orders');
    }
}
