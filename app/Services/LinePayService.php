<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LinePayService
{
    protected string $channelId;
    protected string $channelSecret;
    protected string $url;

    public function __construct()
    {
        // Sandbox URL
        $this->url = config('services.linepay.url', 'https://sandbox-api-pay.line.me');

        $this->channelId = config('services.linepay.channel_id');
        $this->channelSecret = config('services.linepay.channel_secret');
    }

    public function createPayment(Order $order): string
    {
        // 如果還沒有 merchant_order_no 就生成
        if (!$order->merchant_order_no) {
            $merchantOrderNo = now()->format('YmdHis') . rand(1000, 9999);
    
            $order->update([
                'merchant_order_no' => $merchantOrderNo
            ]);
        }
    
        $body = [
            'amount' => (int) round($order->total_amount),
            'currency' => 'TWD',
            'orderId' => $order->merchant_order_no, // 改這裡
            'packages' => [
                [
                    'id' => 'package-1',
                    'amount' => (int) round($order->total_amount),
                    'products' => collect($order->items)->map(function ($item) {
                        return [
                            'id' => 'product-' . $item->id,
                            'name' => $item->product->name,
                            'quantity' => $item->quantity,
                            'price' => (int) round($item->product->price),
                            'imageUrl' => $item->product->image_url ?? null,
                        ];
                    })->toArray(),
                ]
            ],
            'redirectUrls' => [
                'confirmUrl' => route('linepay.confirm'),
                'cancelUrl'  => route('linepay.cancel'),
            ],
        ];
    
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
        $nonce = (string) Str::uuid();
        $path = '/v3/payments/request';
    
        $rawSignature = $this->channelSecret . $path . $bodyJson . $nonce;
        $signature = hash_hmac('sha256', $rawSignature, $this->channelSecret, true);
        $authorization = base64_encode($signature);
    
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-LINE-ChannelId' => $this->channelId,
            'X-LINE-Authorization-Nonce' => $nonce,
            'X-LINE-Authorization' => $authorization,
        ])->send('POST', $this->url . $path, [
            'body' => $bodyJson,
        ]);
    
        $result = $response->json();
    
        if (!isset($result['info']['transactionId'])) {
            throw new \Exception('Line Pay 建立付款失敗: ' . ($result['returnMessage'] ?? '未知錯誤'));
        }
    
        Payment::create([
            'order_id' => $order->id,
            'method' => 'linepay',
            'amount' => $order->total_amount,
            'status' => Payment::STATUS_PENDING,
            'transaction_id' => $result['info']['transactionId'],
        ]);
    
        return $result['info']['paymentUrl']['web'];
    }

    /**
     * 確認付款 (LINE Pay v3)
     */
    public function confirm(string $transactionId, Order $order): bool
    {
        // 生成 Body
        $body = [
            'amount' => (int) round($order->total_amount),
            'currency' => 'TWD',
        ];
    
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
        // 產生 Nonce
        $nonce = (string) Str::uuid();
    
        // API Path
        $path = "/v3/payments/{$transactionId}/confirm";
    
        // 產生簽章
        $rawSignature = $this->channelSecret . $path . $bodyJson . $nonce;
        $signature = hash_hmac('sha256', $rawSignature, $this->channelSecret, true);
        $authorization = base64_encode($signature);
    
        // Log 確認
        \Log::info('LINE Pay Confirm Headers:', [
            'X-LINE-ChannelId' => $this->channelId,
            'X-LINE-Authorization-Nonce' => $nonce,
            'X-LINE-Authorization' => $authorization,
        ]);
        \Log::info('LINE Pay Confirm Body:', $body);
    
        // 發送請求
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-LINE-ChannelId' => $this->channelId,
            'X-LINE-Authorization-Nonce' => $nonce,
            'X-LINE-Authorization' => $authorization,
        ])->send('POST', $this->url . $path, [
            'body' => $bodyJson,
        ]);
    
        $result = $response->json();
    
        // 判斷是否成功
        if (!isset($result['returnCode']) || $result['returnCode'] !== '0000') {
            \Log::error('LINE Pay Confirm Failed:', $result);
            return false;
        }
    
        // 更新 Payment 資料
        $payment = Payment::where('order_id', $order->id)
                          ->where('method', 'linepay')
                          ->latest()
                          ->first();
    
        if ($payment) {
            $payment->update([
                'status' => Payment::STATUS_PAID,
                'transaction_id' => $transactionId,
                'raw_response' => $result,
                'paid_at' => now(),
            ]);
        }
    
        // 更新訂單狀態
        $order->update([
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => Payment::STATUS_PAID,
        ]);
    
        return true;
    }
}