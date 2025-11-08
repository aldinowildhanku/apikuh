<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SecretMessage extends Model
{
    // Tambahkan 'opened_at' ke dalam fillable
    protected $fillable = ['uuid', 'message', 'expires_at', 'opened_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'opened_at' => 'datetime', // Casting untuk opened_at
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }
}