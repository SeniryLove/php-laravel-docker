<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;
use App\Services\EcpayService;
use App\Services\LinePayService;

class PaymentController extends Controller
{
    // 付款跳轉 ECPay
    public function payWithEcpay(Order $order, Request $request, EcpayService $service)
    {
        $this->authorizeOrder($order, $request);
    
        if ($order->payment_status === 'paid') {
            return redirect()->away($this->frontendUrl($order, 'paid', '訂單已付款', 400));
        }
    
        if ($order->status === 'cancelled') {
            return redirect()->away($this->frontendUrl($order, 'paid', '訂單已取消', 400));
        }
    
        $formHtml = $service->createPayment($order);
        return response($formHtml);
    }
    
    // ECPay Callback
    public function ecpayCallback(Request $request, EcpayService $service)
    {
        $data = $request->all();
        \Log::info("handleCallback request data: " . json_encode($data));
        DB::transaction(function () use ($data, $service) {
            $order = $service->handleCallback($data);
    
            if (!$order || $order->payment_status === 'paid') {
                return;
            }
    
            $this->deductStock($order);
    
            $order->update([
                'payment_status' => Order::PAYMENT_PAID,
                'status'         => Order::STATUS_PAID,
                'payment_method' => 'ecpay',
                'transaction_id' => $data['MerchantTradeNo'],
            ]);
        });
    
        // ECPay 要求回應 '1|OK'
        return response('1|OK');
    }

    /**
     * LINE Pay 付款
     */
    public function payWithLinePay(Order $order, Request $request, LinePayService $service)
    {
        $this->authorizeOrder($order, $request);

        if ($order->payment_status === 'paid') {
            return response()->json(['redirect_url' => $this->frontendUrl($order, 'paid', '訂單已付款', 400)], 200);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['redirect_url' => $this->frontendUrl($order, 'paid', '訂單已取消', 400)], 200);
        }

        $url = $service->createPayment($order);

        return response()->json([
            'redirect_url' => $url
        ]);
    }

    /**
     * LINE Pay Confirm（付款成功才扣庫存）
     */
    public function linePayConfirm(Request $request, LinePayService $service)
    {
        $request->validate([
            'orderId' => 'required|exists:orders,merchant_order_no',
            'transactionId' => 'required|string',
        ]);
    
        $order = Order::where('merchant_order_no', $request->orderId)->firstOrFail();
    
        // 檢查是否已付款
        if ($order->payment_status === Order::PAYMENT_PAID) {
            return redirect()->away($this->frontendUrl($order, 'paid', '訂單已付款', 400));
        }
    
        try {
            DB::transaction(function () use ($request, $service, $order) {
    
                // 呼叫 LinePayService 確認付款
                $success = $service->confirm($request->transactionId, $order);
    
                if (!$success) {
                    throw new \Exception('Line Pay 付款失敗');
                }
    
                // 扣庫存
                $this->deductStock($order);
    
                // 更新訂單付款狀態與流程狀態
                $order->update([
                    'payment_status' => Order::PAYMENT_PAID,
                    'status' => Order::STATUS_PAID,
                    'payment_method' => 'linepay',
                    'transaction_id' => $request->transactionId,
                ]);
            });
    
            // 付款成功 → 轉跳前端
            return redirect()->away($this->frontendUrl($order, 'success', '付款成功', 200));
    
        } catch (\Exception $e) {
            \Log::error('Line Pay confirm failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'transaction_id' => $request->transactionId,
            ]);
    
            // 付款失敗 → 轉跳前端
            return redirect()->away($this->frontendUrl($order, 'failed', '付款失敗，請稍後再試', 500));
        }
    }
    
    /**
     * LINE Pay Cancel
     */
    public function linePayCancel(Request $request)
    {
        $orderId = $request->get('orderId');
        $order = Order::where('merchant_order_no', $orderId)->first();
    
        if (!$order) {
            return redirect()->away($this->frontendUrl($order ?? new Order(), 'failed', '找不到訂單', 404));
        }
    
        return redirect()->away($this->frontendUrl($order, 'cancelled', '使用者取消付款', 200));
    }
    
    /**
     * 統一產生前端轉跳 URL
     */
    private function frontendUrl(Order $order, string $status, string $message, int $code)
    {
        $frontend = config('app.frontend_url', 'http://localhost:5173');
    
        return $frontend . '/#/payment/result?' . http_build_query([
            'order_id' => $order->id ?? 0,
            'status'   => $status,
            'message'  => $message,
            'code'     => $code,
        ]);
    }

    /**
     * 扣庫存（統一集中處理）
     */
    private function deductStock(Order $order)
    {
        foreach ($order->items as $item) {

            $product = Product::lockForUpdate()
                ->findOrFail($item->product_id);

            if ($product->stock < $item->quantity) {
                throw new \Exception("商品 {$product->name} 庫存不足");
            }

            $product->decrement('stock', $item->quantity);
        }
    }

    /**
     * 驗證訂單屬於當前使用者
     */
    private function authorizeOrder(Order $order, Request $request)
    {
        $user = $request->user();
    
        if (!$user) {
            abort(401, '請先登入');
        }
    
        if ($order->user_id !== $user->id) {
            abort(403, '無權限操作此訂單');
        }
    }
}
