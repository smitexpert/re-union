<?php

namespace App\Services\Sms;

use App\Models\ReunionGuest;
use App\Models\ReunionGuestFee;
use App\Models\ReunionRegistration;
use App\Models\SmsLog;
use App\Support\BangladeshPhone;

class SendRegistrationSms
{
    public function send(ReunionRegistration $registration, string $trigger): SmsLog
    {
        $phone = BangladeshPhone::toE164((string) ($registration->student?->phone ?? ''));
        $client = app(BulkSmsBdClient::class);

        $base = [
            'registration_id' => $registration->id,
            'student_id' => $registration->student_id,
            'trigger' => $trigger,
            'phone' => $phone ?? null,
            'message' => '',
        ];

        // Missing phone → skipped.
        if ($phone === null) {
            return $this->writeLog($base + [
                'status' => 'skipped',
                'error_message' => 'student phone is missing or unparseable',
            ]);
        }

        // Credentials missing → skipped.
        if (! $client->isConfigured()) {
            return $this->writeLog($base + [
                'status' => 'skipped',
                'error_message' => 'bulksms credentials missing in services.bulksms config',
            ]);
        }

        $base['message'] = $this->buildMessage($registration, $trigger);

        $result = $client->send($phone, $base['message']);

        if ($result['ok']) {
            return $this->writeLog($base + [
                'status' => 'sent',
                'http_status' => $result['http_status'],
                'provider_response' => $this->truncate((string) ($result['body'] ?? '')),
                'sent_at' => now(),
            ]);
        }

        return $this->writeLog($base + [
            'status' => 'failed',
            'http_status' => $result['http_status'],
            'provider_response' => $this->truncate((string) ($result['body'] ?? '')),
            'error_message' => $result['exception'] ?? 'provider returned non-2xx',
            'sent_at' => now(),
        ]);
    }

    private function writeLog(array $data): SmsLog
    {
        return SmsLog::create($data);
    }

    private function truncate(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        return mb_substr($body, 0, 1024);
    }

    private function buildMessage(ReunionRegistration $registration, string $trigger): string
    {
        $reunionName = $registration->reunion?->title ?? 'Reunion';
        $code = $registration->registration_code;
        $amount = number_format((float) $registration->total_fee, 0, '.', ',');

        if ($trigger === 'payment_refunded') {
            return sprintf(
                '%s: Your registration %s has been cancelled and a refund has been issued.',
                $reunionName,
                $code,
            );
        }

        // payment_paid and manual_resend both produce the confirmation message.
        [$adultCount, $childCount] = $this->guestBreakdown($registration);

        return sprintf(
            "%s: Registration Confirmed!\nCode: %s\nAmount: ৳%s\nGuests: %d adult, %d child\nThank you.",
            $reunionName,
            $code,
            $amount,
            $adultCount,
            $childCount,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function guestBreakdown(ReunionRegistration $registration): array
    {
        $reunionId = $registration->reunion_id;
        $activeTypes = ReunionGuestFee::query()
            ->where('reunion_id', $reunionId)
            ->where('is_active', true)
            ->pluck('guest_type')
            ->all();

        if ($registration->relationLoaded('guests')) {
            $guests = $registration->guests;
        } else {
            $guests = ReunionGuest::where('registration_id', $registration->id)->get();
        }

        $active = $guests->whereIn('guest_type', $activeTypes);

        return [
            $active->where('guest_type', 'Adult')->count(),
            $active->where('guest_type', 'Child')->count(),
        ];
    }
}
