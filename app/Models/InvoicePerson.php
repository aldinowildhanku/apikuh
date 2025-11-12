<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicePerson extends Model
{
    use HasFactory;
    protected $table = 'invoice_persons';

    protected $fillable = [
        'invoice_id',
        'person_name',
        'person_total_amount',
        'amount_to_pay_after_prorate',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}