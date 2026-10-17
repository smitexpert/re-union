<?php

namespace Tests\Feature;

use App\Models\Reunion;
use App\Models\ReunionGuest;
use App\Models\ReunionGuestFee;
use App\Models\ReunionRegistration;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\Sms\SendRegistrationSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendRegistrationSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);
    }

    private function makeRegistrationWithGuests(string $paymentStatus = 'unpaid'): ReunionRegistration
    {
        $reunion = Reunion::factory()->create([
            'title' => 'SWAATC Reunion 2026',
            'registration_fee' => 500.00,
        ]);
        ReunionGuestFee::factory()->create(['reunion_id' => $reunion->id, 'guest_type' => 'Adult', 'fee' => 300.00, 'is_active' => true]);
        ReunionGuestFee::factory()->create(['reunion_id' => $reunion->id, 'guest_type' => 'Child', 'fee' => 150.00, 'is_active' => true]);

        $student = Student::factory()->create(['phone' => '01712345678']);
        $registration = ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->id,
            'student_id' => $student->id,
            'total_fee' => '1100.00',
            'guest_count' => 3,
            'payment_status' => $paymentStatus,
        ]);
        foreach (['Adult', 'Adult', 'Child'] as $type) {
            ReunionGuest::create([
                'registration_id' => $registration->id,
                'guest_type' => $type,
                'fee' => $type === 'Adult' ? '300.00' : '150.00',
            ]);
        }

        return $registration->fresh(['reunion', 'student', 'guests']);
    }

    public function test_sends_confirmation_sms_with_normalized_phone_and_message(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('OK', 200)]);

        $registration = $this->makeRegistrationWithGuests('unpaid');
        $log = app(SendRegistrationSms::class)->send($registration, 'payment_paid');

        $this->assertSame('sent', $log->status);
        $this->assertSame('8801712345678', $log->phone);
        $this->assertSame('payment_paid', $log->trigger);
        $this->assertSame(200, $log->http_status);
        $this->assertStringContainsString('SWAATC Reunion 2026: Registration Confirmed', $log->message);
        $this->assertStringContainsString($registration->registration_code, $log->message);
        $this->assertStringContainsString('1,100', $log->message);
        $this->assertStringContainsString('2 adult', $log->message);
        $this->assertStringContainsString('1 child', $log->message);

        Http::assertSent(fn ($r) => $r->data()['number'] === '8801712345678'
            && $r->data()['api_key'] === 'test-key'
            && $r->data()['senderid'] === 'TESTID');
    }

    public function test_sends_cancellation_sms_when_trigger_is_refunded(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('OK', 200)]);

        $registration = $this->makeRegistrationWithGuests('paid');
        $log = app(SendRegistrationSms::class)->send($registration, 'payment_refunded');

        $this->assertSame('sent', $log->status);
        $this->assertSame('payment_refunded', $log->trigger);
        $this->assertStringContainsString('cancelled', $log->message);
        $this->assertStringContainsString('refund', $log->message);
        $this->assertStringContainsString($registration->registration_code, $log->message);
    }

    public function test_writes_skipped_log_when_phone_is_missing(): void
    {
        // The students.phone column is NOT NULL, so an empty/unparseable string
        // is the realistic way to model a missing phone at the service layer.
        $registration = $this->makeRegistrationWithGuests('unpaid');
        $registration->student->update(['phone' => 'not-a-phone']);

        $log = app(SendRegistrationSms::class)->send($registration->fresh(['student']), 'payment_paid');

        $this->assertSame('skipped', $log->status);
        $this->assertNull($log->http_status);
        $this->assertStringContainsString('phone', strtolower((string) $log->error_message));
        Http::assertNothingSent();
        $this->assertSame(1, SmsLog::count());
    }

    public function test_writes_skipped_log_when_credentials_missing(): void
    {
        config(['services.bulksms.api_key' => '']);

        $registration = $this->makeRegistrationWithGuests('unpaid');

        $log = app(SendRegistrationSms::class)->send($registration, 'payment_paid');

        $this->assertSame('skipped', $log->status);
        $this->assertStringContainsString('credentials', strtolower((string) $log->error_message));
        Http::assertNothingSent();
    }

    public function test_writes_failed_log_when_provider_returns_2xx_with_business_failure_response_code(): void
    {
        $body = json_encode([
            'response_code' => 1006,
            'success_message' => '',
            'error_message' => 'Unsuccessfull!! Balance Validity Not Available in this naharsharmin Account',
        ]);

        Http::fake(['bulksmsbd.net/*' => Http::response($body, 200)]);

        $registration = $this->makeRegistrationWithGuests('unpaid');
        $log = app(SendRegistrationSms::class)->send($registration, 'payment_paid');

        $this->assertSame('failed', $log->status);
        $this->assertSame(1006, $log->provider_code);
        $this->assertSame(200, $log->http_status);
        $this->assertStringContainsString('Balance Validity Not Available', (string) $log->error_message);
        $this->assertNotNull($log->sent_at);
    }

    public function test_writes_failed_log_when_provider_returns_5xx_but_sms_log_still_created(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('Server Error', 500)]);

        $registration = $this->makeRegistrationWithGuests('unpaid');
        $log = app(SendRegistrationSms::class)->send($registration, 'payment_paid');

        $this->assertSame('failed', $log->status);
        $this->assertSame(500, $log->http_status);
        $this->assertSame('Server Error', $log->provider_response);
        $this->assertNotNull($log->sent_at);
    }
}
