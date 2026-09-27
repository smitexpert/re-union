<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'graduation_year',
        'department',
        'session',
        'current_occupation',
        'present_address',
        'permanent_address',
        'office_address',
        'photo',
    ];

    public function registrations(): HasMany
    {
        return $this->hasMany(ReunionRegistration::class);
    }
}
