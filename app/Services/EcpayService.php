<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class EcpayService
{
    protected string $merchantId;
    protected string $hashKey;
    protected string $hashIv;
    protected string $url;

    public function __construct()
    {
        $this->merchantId   = config('services.ecpay.merchant_id');
        $this->hashKey      = config('services.ecpay.hash_key');
        $this->hashIv       = config('services.ecpay.hash_iv');
        $this->url          = config('services.ecpay.url');
        $this->callback_url = config('services.ecpay.callback_url');
        $this->order_result_url = config('services.ecpay.order_result_url');
    }

    /**
     * 建立付款表單 HTML（無 iframe，整頁跳轉 ECPay）
     */
    public function createPayment(Order $order): string
    {
        $merchantTradeNo = 'ORD' . time() . rand(1000, 9999);

        // 建立 Payment 資料
        Payment::create([
            'order_id'       => $order->id,
            'method'         => 'ecpay',
            'amount'         => $order->total_amount,
            'status'         => 'pending',
            'transaction_id' => $merchantTradeNo,
        ]);

        $params = [
            'MerchantID'        => $this->merchantId,
            'MerchantTradeNo'   => $merchantTradeNo,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType'       => 'aio',
            'TotalAmount'       => (int)$order->total_amount,
            'TradeDesc'         => 'Order Payment',
            'ItemName'          => 'Order #' . $order->id,
            'ReturnURL'         => $this->callback_url, // server callback
            //'OrderResultURL'    => $this->order_result_url, // client callback(Stage不一定會用ReturnURL因此暫時用後端接收結果)
            'ClientBackURL'     => config('app.frontend_url') . "/#/orders/{$order->id}", // SPA 結果頁
            'ChoosePayment'     => 'ALL',
            'EncryptType'       => 1,
        ];
        
        $params['CheckMacValue'] = $this->generateCheckMacValue($params);

        // 自動提交表單 HTML
        $form = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Redirect to ECPay</title></head><body>";
        $form .= "<form id='ecpay_form' method='post' action='{$this->url}'>";
        foreach ($params as $key => $value) {
            $form .= "<input type='hidden' name='{$key}' value='{$value}' />";
        }
        $form .= "</form>";
        $form .= "<script>document.getElementById('ecpay_form').submit();</script>";
        $form .= "</body></html>";

        return $form;
    }

    /**
     * Callback 處理，回傳 Order
     */
    public function handleCallback(array $data): ?Order
    {
        
        if ($data['RtnCode'] != 1) {
            return null;
        }

        $payment = Payment::where('transaction_id', $data['MerchantTradeNo'])->first();

        if (!$payment || $payment->status === 'paid') {
            return null;
        }

        $payment->update([
            'status'       => 'paid',
            'raw_response' => $data,
            'paid_at'      => now(),
        ]);

        $order = $payment->order;
        $order->update(['status' => 'processing']);

        return $order;
    }

    /**
     * 產生 CheckMacValue
     */
    protected function generateCheckMacValue(array $params): string
    {
        unset($params['CheckMacValue']);
        ksort($params);

        $encoded = "HashKey={$this->hashKey}";
        foreach ($params as $key => $value) {
            $encoded .= "&{$key}={$value}";
        }
        $encoded .= "&HashIV={$this->hashIv}";

        $encoded = urlencode($encoded);
        $encoded = strtolower($encoded);

        return strtoupper(hash('sha256', $encoded));
    }
}
