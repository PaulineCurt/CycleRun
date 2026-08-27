<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Avatar;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'avatar', 'typical_cycle_length', 'typical_menses_length', 'birth_date',
    'cycle_length_override', 'menses_length_override',
    'terms_accepted_at', 'privacy_accepted_at', 'data_usage_accepted_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'avatar' => Avatar::class,
            'typical_cycle_length' => 'integer',
            'typical_menses_length' => 'integer',
            'cycle_length_override' => 'integer',
            'menses_length_override' => 'integer',
            'birth_date' => 'date:Y-m-d',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'data_usage_accepted_at' => 'datetime',
        ];
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function dayLogs(): HasMany
    {
        return $this->hasMany(DayLog::class);
    }

    public function exclusionPeriods(): HasMany
    {
        return $this->hasMany(ExclusionPeriod::class);
    }
}
