<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reunion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'event_date',
        'registration_deadline',
        'is_active',
        'registration_fee',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'registration_deadline' => 'date',
            'is_active' => 'boolean',
            'registration_fee' => 'decimal:2',
        ];
    }

    public function guestFees(): HasMany
    {
        return $this->hasMany(ReunionGuestFee::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ReunionRegistration::class);
    }

    public static function currentOpenForRegistration(): ?self
    {
        $today = now()->toDateString();

        return static::query()
            ->where('is_active', true)
            ->whereDate('event_date', '>=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('registration_deadline')
                    ->orWhereDate('registration_deadline', '>=', $today);
            })
            ->orderBy('event_date')
            ->first();
    }
}
