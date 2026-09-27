<?php

namespace App\Models;

use App\Services\RegistrationFeeCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReunionRegistration extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ReunionRegistration $registration) {
            $registration->registration_code ??= static::generateRegistrationCode();
        });
    }

    public static function generateRegistrationCode(): string
    {
        do {
            $code = 'REG-'.strtoupper(Str::random(8));
        } while (static::where('registration_code', $code)->exists());

        return $code;
    }

    protected $fillable = [
        'reunion_id',
        'student_id',
        'tshirt_size',
        'guest_count',
        'total_fee',
        'registration_status',
        'payment_status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_fee' => 'decimal:2',
            'guest_count' => 'integer',
        ];
    }

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(ReunionGuest::class, 'registration_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'registration_id');
    }

    /**
     * Create a registration atomically: persist the registration row and one
     * ReunionGuest row per guest, recomputing total_fee from the calculator.
     *
     * @param  array{guest_count_adult?: int, guest_count_child?: int}  $payload
     */
    public static function createFor(
        Reunion $reunion,
        Student $student,
        array $payload,
        string $registrationStatus,
        ?string $tshirtSize,
        ?string $notes,
    ): self {
        $adultCount = (int) ($payload['guest_count_adult'] ?? 0);
        $childCount = (int) ($payload['guest_count_child'] ?? 0);

        $totalFee = RegistrationFeeCalculator::total($reunion, $adultCount, $childCount);
        $totalGuests = $adultCount + $childCount;

        $registration = static::create([
            'reunion_id' => $reunion->id,
            'student_id' => $student->id,
            'tshirt_size' => $tshirtSize,
            'guest_count' => $totalGuests,
            'total_fee' => $totalFee,
            'registration_status' => $registrationStatus,
            'payment_status' => 'unpaid',
            'notes' => $notes,
        ]);

        $activeFees = ReunionGuestFee::query()
            ->where('reunion_id', $reunion->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('guest_type');

        $rows = [];
        if ($adultCount > 0 && ($fee = $activeFees->get('Adult'))) {
            for ($i = 0; $i < $adultCount; $i++) {
                $rows[] = [
                    'registration_id' => $registration->id,
                    'guest_type' => 'Adult',
                    'fee' => (string) $fee->fee,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($childCount > 0 && ($fee = $activeFees->get('Child'))) {
            for ($i = 0; $i < $childCount; $i++) {
                $rows[] = [
                    'registration_id' => $registration->id,
                    'guest_type' => 'Child',
                    'fee' => (string) $fee->fee,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            ReunionGuest::insert($rows);
        }

        return $registration;
    }
}
