<?php

namespace App\Models;

use App\Enums\Flow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'flow', 'pain', 'energy', 'symptoms', 'note'])]
class DayLog extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'flow' => Flow::class,
            'pain' => 'integer',
            'energy' => 'integer',
            'symptoms' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
