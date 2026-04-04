<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'command',
        'payload',
        'status',
        'response',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
