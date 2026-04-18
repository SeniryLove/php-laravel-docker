<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // 取得我的訂單列表
    public function index(Request $request)
    {
        $orders = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    // 取得單筆訂單
    public function show(Request $request, $id)
    {
        $order = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    // 取消訂單
    public function cancel(Request $request, $id)
    {
        $order = Order::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => '找不到該訂單或無權限'
            ], 404);
        }

        // 只能取消待付款訂單
        if ($order->status !== 'pending') {
            $statusMessages = [
                'processing' => '訂單正在處理中，無法取消',
                'paid' => '訂單已付款，無法取消',
                'shipped' => '訂單已出貨，無法取消',
                'completed' => '訂單已完成，無法取消',
                'cancelled' => '訂單已取消，無法再次取消',
            ];

            $message = $statusMessages[$order->status] ?? '無法取消此訂單';

            return response()->json([
                'success' => false,
                'message' => $message
            ], 400);
        }

        // 取消訂單
        DB::transaction(function () use ($order) {
            $order->status = 'cancelled';
            $order->save();
        });

        return response()->json([
            'success' => true,
            'message' => '訂單已取消成功'
        ]);
    }
}