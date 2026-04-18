<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'name',
        'description',
        'price',
        'stock',
        'image_url',
        'status',
        'is_active'
    ];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'bool',
    ];
}
