<?php

namespace App\Models;

use App\Enums\ExclusionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'started_on', 'ended_on'])]
class ExclusionPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ExclusionType::class,
            'started_on' => 'date:Y-m-d',
            'ended_on' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
