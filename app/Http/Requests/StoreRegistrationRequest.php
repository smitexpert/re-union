<?php

namespace App\Http\Requests;

use App\Enums\StudentOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student.full_name' => ['required', 'string', 'max:150'],
            'student.phone' => ['required', 'string', 'max:20'],
            'student.graduation_year' => ['required', 'integer', 'between:1950,2100'],
            'student.option' => ['required', Rule::enum(StudentOption::class)],
            'student.session' => ['required', 'string', 'max:255'],
            'student.current_occupation' => ['required', 'string', 'max:150'],
            'student.present_address' => ['required', 'string'],
            'student.permanent_address' => ['nullable', 'string'],
            'student.office_address' => ['nullable', 'string'],
            // The frontend submits the photo as `student[photo]` (see
            // Wizard's buildFormData). PHP/Symfony promote that bracketed
            // name into a nested UploadedFile, which Laravel validates via
            // dot notation — so the rule lives at `student.photo`, not at a
            // top-level `photo` key.
            //
            // 5 MB mirrors the server-side upload ceiling (nginx
            // `client_max_body_size` / PHP `upload_max_filesize`). The
            // browser resizes photos before sending them, so this is only a
            // backstop for uploads that arrive uncompressed.
            'student.photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'tshirt_size' => ['required', 'string', 'max:10'],
            'guest_count_adult' => ['required', 'integer', 'min:0'],
            'guest_count_child' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
