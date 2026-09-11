<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'finding_code',
        'category',
        'severity',
        'score_impact',
        'title',
        'evidence',
        'description',
        'recommendation',
    ];

    protected $casts = [
        'score_impact' => 'integer',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function getSeverityBadgeColorAttribute(): string
    {
        return match (strtoupper($this->severity)) {
            'CRITICAL' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
            'HIGH' => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
            'MODERATE' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            'LOW' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            default => 'bg-gray-500/10 text-gray-400 border-gray-500/30',
        };
    }

    public function getSeverityIconAttribute(): string
    {
        return match (strtoupper($this->severity)) {
            'CRITICAL' => '🔴',
            'HIGH' => '🟠',
            'MODERATE' => '🟡',
            'LOW' => '🟢',
            default => '⚪',
        };
    }
}
