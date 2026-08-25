<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'started_on', 'menses_ended_on'])]
class Cycle extends Model
{
    protected function casts(): array
    {
        return [
            'started_on' => 'date:Y-m-d',
            'menses_ended_on' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
