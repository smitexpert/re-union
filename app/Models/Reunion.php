<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Reunion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'event_date',
        'registration_deadline',
        'is_active',
        'registration_fee',
        'charge_type',
        'charge_value',
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
            'charge_value' => 'decimal:2',
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

    /**
     * Every payment made against any registration of this reunion.
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,
            ReunionRegistration::class,
            'reunion_id',
            'registration_id',
        );
    }

    public static function currentOpenForRegistration(): ?self
    {
        $today = now()->toDateString();

        $check = static::query()
            ->where('is_active', true)
            ->whereDate('event_date', '>=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('registration_deadline')
                    ->orWhereDate('registration_deadline', '>=', $today);
            })
            ->orderBy('event_date')
            ->first();

        return $check;
    }

    public function isChargeEnabled(): bool
    {
        return $this->charge_type !== null
            && $this->charge_value !== null
            && (float) $this->charge_value > 0.0;
    }
}
