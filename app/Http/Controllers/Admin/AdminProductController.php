<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminProductController extends Controller
{
    // 後台商品列表（全部）
    public function index()
    {
        return response()->json(
            Product::orderBy('created_at', 'desc')->paginate(20)
        );
    }

    // 新增商品
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image_url' => 'nullable|string',
        ]);

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    // 更新商品
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'image_url' => 'nullable|string',
            'status' => 'in:draft,published',
            'is_active' => 'boolean',
        ]);

        $product->update($data);

        return response()->json($product);
    }

    // 上下架切換
    public function toggle($id)
    {
        $product = Product::findOrFail($id);

        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'message' => '狀態已更新',
            'is_active' => $product->is_active
        ]);
    }

    // 刪除商品
    public function destroy($id)
    {
        Product::findOrFail($id)->delete();

        return response()->json([
            'message' => '商品已刪除'
        ]);
    }
}