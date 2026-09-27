<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReunionGuestFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'reunion_id',
        'guest_type',
        'fee',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }
}
