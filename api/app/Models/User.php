<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'typical_cycle_length', 'typical_menses_length', 'birth_date'])]
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
            'typical_cycle_length' => 'integer',
            'typical_menses_length' => 'integer',
            'birth_date' => 'date:Y-m-d',
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
