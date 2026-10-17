<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'student_id',
        'phone',
        'message',
        'trigger',
        'status',
        'http_status',
        'provider_response',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ReunionRegistration::class, 'registration_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
