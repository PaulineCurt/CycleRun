<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'program_id', 'planned_date', 'week_index', 'type', 'planned_load',
    'target_pace_min_s', 'target_pace_max_s', 'base_generated', 'adaptation',
    'status', 'actual',
])]
class TrainingSession extends Model
{
    protected function casts(): array
    {
        return [
            'planned_date' => 'date:Y-m-d',
            'week_index' => 'integer',
            'type' => SessionType::class,
            'planned_load' => 'array',
            'target_pace_min_s' => 'integer',
            'target_pace_max_s' => 'integer',
            'base_generated' => 'array',
            'adaptation' => 'array',
            'status' => SessionStatus::class,
            'actual' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
