<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderInventoryOrderItem extends Model
{
    use BelongsToAccount;
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'for_customer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderInventoryOrder::class, 'order_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id')->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
