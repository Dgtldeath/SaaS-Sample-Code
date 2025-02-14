<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderInventoryOrder extends Model
{
    use BelongsToAccount;
    use HasFactory;

    public $timestamps = false;

    protected $casts = [
        'account_id' => 'integer',
        'is_pickup' => 'boolean',
        'pickup_date_timestamp' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'account_id',
        'sub_total',
        'status',
        'comments',
        'customer_name',
        'is_pickup',
        'pickup_by',
        'pickup_date_timestamp',
        'order_notes',
        'store_number',
        'vendor_order_id',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id'); // First try direct ID
    }


    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderInventoryOrderItem::class, 'order_id');
    }
}
