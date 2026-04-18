<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    // 商品列表（只顯示已上架）
    public function index()
    {
        $products = Product::where('status', 'published')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->paginate(12);
        
        return response()->json($products);
    }

    // 商品詳情
    public function show($id)
    {
        $product = Product::where('status', 'published')
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json($product);
    }
}