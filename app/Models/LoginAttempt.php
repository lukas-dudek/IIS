<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['login', 'ip', 'successful', 'attempted_at'];

    protected $casts = [
        'successful' => 'boolean',
        'attempted_at' => 'datetime',
    ];
}
