<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'total_before_discount',
        'discount_amount',
        'shipping_cost',
        'service_charge',
        'grand_total',
        'bank_name',
        'account_number',
        'qris_image_url',
    ];

    public function persons()
    {
        return $this->hasMany(InvoicePerson::class);
    }
}