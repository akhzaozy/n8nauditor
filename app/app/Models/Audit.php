<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Audit extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'score',
        'risk_level',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'failed_services_count',
        'firewall_status',
        'ssh_port',
        'raw_payload',
        'status',
        'audited_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'cpu_usage' => 'float',
        'memory_usage' => 'float',
        'disk_usage' => 'float',
        'failed_services_count' => 'integer',
        'raw_payload' => 'array',
        'audited_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class)->orderByRaw("
            CASE severity
                WHEN 'CRITICAL' THEN 1
                WHEN 'HIGH' THEN 2
                WHEN 'MODERATE' THEN 3
                WHEN 'LOW' THEN 4
                ELSE 5
            END
        ");
    }

    public function aiReport(): HasOne
    {
        return $this->hasOne(AiReport::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function getRiskBadgeColorAttribute(): string
    {
        return match (strtoupper($this->risk_level)) {
            'LOW' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'MODERATE' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            'HIGH' => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
            'CRITICAL' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
            default => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
        };
    }

    public function getScoreHexColorAttribute(): string
    {
        return match (strtoupper($this->risk_level)) {
            'LOW' => '#10b981',
            'MODERATE' => '#f59e0b',
            'HIGH' => '#f97316',
            'CRITICAL' => '#ef4444',
            default => '#a855f7',
        };
    }
}
