<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'os_info',
        'description',
        'api_token',
        'status',
        'last_audited_at',
    ];

    protected $casts = [
        'last_audited_at' => 'datetime',
    ];

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class)->orderBy('audited_at', 'desc');
    }

    public function latestAudit(): HasOne
    {
        return $this->hasOne(Audit::class)->latestOfMany('audited_at');
    }
}
