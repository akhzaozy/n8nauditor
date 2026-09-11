<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'provider',
        'model',
        'summary',
        'overall_assessment',
        'findings_analysis',
        'raw_response',
    ];

    protected $casts = [
        'findings_analysis' => 'array',
        'raw_response' => 'array',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
