<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_person_id',
        'item_name',
        'item_price',
    ];

    public function person()
    {
        return $this->belongsTo(InvoicePerson::class, 'invoice_person_id');
    }
}