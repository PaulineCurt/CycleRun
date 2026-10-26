<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\Methodology;
use App\Enums\ProgramStatus;
use App\Services\Training\VmaProvenance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'goal_distance_m', 'goal_time_s', 'methodology', 'level', 'vma_source',
    'level_input', 'paces', 'duration_weeks', 'days_per_week', 'start_date', 'status',
    'feasibility', 'warnings',
])]
class Program extends Model
{
    protected function casts(): array
    {
        return [
            'goal_distance_m' => 'integer',
            'goal_time_s' => 'integer',
            'methodology' => Methodology::class,
            'level' => Level::class,
            'vma_source' => VmaProvenance::class,
            'level_input' => 'array',
            'paces' => 'array',
            'duration_weeks' => 'integer',
            'days_per_week' => 'integer',
            'start_date' => 'date:Y-m-d',
            'status' => ProgramStatus::class,
            'feasibility' => 'array',
            'warnings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
}
