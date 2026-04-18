<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        $user = $request->user();

        $order = DB::transaction(function () use ($data, $user) {

            $total = 0;

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_amount' => 0
            ]);

            foreach ($data['items'] as $item) {

                $product = Product::lockForUpdate()->findOrFail($item['id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("商品 {$product->name} 庫存不足");
                }

                $subtotal = $product->price * $item['quantity'];
                $total += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal
                ]);

                // ❌ 不扣庫存
            }

            $order->update([
                'total_amount' => $total
            ]);

            return $order->load('items.product');
        });

        return response()->json([
            'message' => '訂單建立成功，請前往付款',
            'order' => $order
        ], 201);
    }
}