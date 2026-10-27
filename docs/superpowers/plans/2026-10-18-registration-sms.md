# Registration SMS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When an admin changes a registration's `payment_status` to `paid` or `refunded` on the Filament view page, the system calls `bulksmsbd.net` synchronously and writes an `sms_logs` row. Admins can also click a "Resend Confirmation SMS" action.

**Architecture:** Thin service layer (`SendRegistrationSms` + `BulkSmsBdClient`) over Laravel's HTTP client. Filament page hooks `afterSave()` for transitions and a header action for manual re-send. Phone normalization lives in a small helper class. All audit rows persist via `SmsLog` model.

**Tech Stack:** PHP 8.5, Laravel 13, Filament 4, PHPUnit 12 (`vendor/bin/phpunit`), SQLite in-memory test DB, `Http::fake()` for HTTP stubs.

---

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_10_18_000000_create_sms_logs_table.php` | Schema for the audit table |
| `app/Models/SmsLog.php` | Eloquent model for `sms_logs` |
| `app/Support/BangladeshPhone.php` | Static helper to normalize BD phone numbers |
| `app/Services/Sms/BulkSmsBdClient.php` | Thin wrapper around `Http::asForm()` for the API |
| `app/Services/Sms/SendRegistrationSms.php` | Single service that builds the message, calls the client, writes the log |
| `app/Filament/Resources/Registrations/Pages/ViewRegistration.php` | Add `afterSave()` + `resendSms` header action |
| `config/services.php` | Add `bulksms` block |
| `.env.example` | Add `BULKSMS_API_KEY`, `BULKSMS_SENDER_ID` |
| `database/factories/ReunionRegistrationFactory.php` | NEW factory so tests can build registrations |
| `tests/Unit/BangladeshPhoneTest.php` | Helper unit tests |
| `tests/Feature/AdminRegistrationSmsTest.php` | Integration tests for transitions and re-send action |

---

## Task 1: Migration for `sms_logs`

**Files:**
- Create: `database/migrations/2026_10_18_000000_create_sms_logs_table.php`

- [ ] **Step 1: Generate the migration file skeleton**

```bash
cd /mnt/c3c37cc3-52e9-4cf6-887a-7d8eab6bda76/Workspace/Molla/re-union/reunion-backend
touch database/migrations/2026_10_18_000000_create_sms_logs_table.php
```

- [ ] **Step 2: Write the migration content**

Replace the empty file with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')
                ->nullable()
                ->constrained('reunion_registrations')
                ->nullOnDelete();
            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();
            $table->string('phone', 20);
            $table->text('message');
            $table->string('trigger', 32);
            $table->string('status', 16);
            $table->unsignedInteger('http_status')->nullable();
            $table->text('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `2026_10_18_000000_create_sms_logs_table` runs successfully against the configured DB.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_10_18_000000_create_sms_logs_table.php
git commit -m "feat(db): add sms_logs audit table"
```

---

## Task 2: `SmsLog` model

**Files:**
- Create: `app/Models/SmsLog.php`

- [ ] **Step 1: Write the model**

```php
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
```

- [ ] **Step 2: Verify with artisan tinker**

```bash
php artisan tinker --execute="echo get_class(new App\Models\SmsLog);"
```

Expected: prints `App\Models\SmsLog`.

- [ ] **Step 3: Commit**

```bash
git add app/Models/SmsLog.php
git commit -m "feat(model): add SmsLog"
```

---

## Task 3: `BangladeshPhone` helper — failing test

**Files:**
- Create: `tests/Unit/BangladeshPhoneTest.php`
- Create: `app/Support/BangladeshPhone.php`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Support\BangladeshPhone;
use PHPUnit\Framework\TestCase;

class BangladeshPhoneTest extends TestCase
{
    public function test_normalizes_leading_zero_to_880(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('01712345678'));
    }

    public function test_passes_through_already_normalized_e164(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('8801712345678'));
    }

    public function test_strips_non_digit_characters(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('+88 017-1234 5678'));
    }

    public function test_prepends_880_to_ten_digit_raw_number(): void
    {
        $this->assertSame('8801712345678', BangladeshPhone::toE164('1712345678'));
    }

    public function test_returns_null_for_empty(): void
    {
        $this->assertNull(BangladeshPhone::toE164(''));
    }

    public function test_returns_null_for_garbage(): void
    {
        $this->assertNull(BangladeshPhone::toE164('abc12'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/Unit/BangladeshPhoneTest.php
```

Expected: FAIL — `Class "App\Support\BangladeshPhone" not found`.

- [ ] **Step 3: Implement the helper**

```php
<?php

namespace App\Support;

class BangladeshPhone
{
    /**
     * Normalize a Bangladesh phone number to bd-bulksms format (8801XXXXXXXXX, 13 digits).
     * Returns null when the input is unusable.
     */
    public static function toE164(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '880' . substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880' . $digits;
        }

        return null;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
vendor/bin/phpunit tests/Unit/BangladeshPhoneTest.php
```

Expected: 6 tests, all PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/BangladeshPhone.php tests/Unit/BangladeshPhoneTest.php
git commit -m "feat(sms): add BangladeshPhone normalizer + tests"
```

---

## Task 4: `BulkSmsBdClient` — failing test

**Files:**
- Create: `tests/Feature/BulkSmsBdClientTest.php`
- Create: `app/Services/Sms/BulkSmsBdClient.php`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: Add `bulksms` config block**

Edit `config/services.php`, append before the `];`:

```php
    'bulksms' => [
        'api_key' => env('BULKSMS_API_KEY'),
        'sender_id' => env('BULKSMS_SENDER_ID'),
        'url' => env('BULKSMS_URL', 'http://bulksmsbd.net/api/smsapi'),
    ],
```

The file should now end with `... 'sender_id' => ..., 'url' => ..., ], ];`.

- [ ] **Step 2: Add env entries to `.env.example`**

Append at the bottom:

```
BULKSMS_API_KEY=
BULKSMS_SENDER_ID=
```

- [ ] **Step 3: Write the failing client test**

```php
<?php

namespace Tests\Feature;

use App\Services\Sms\BulkSmsBdClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkSmsBdClientTest extends TestCase
{
    public function test_sends_form_post_to_provider_with_configured_credentials(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
            'services.bulksms.url' => 'http://bulksmsbd.net/api/smsapi',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response('OK', 200),
        ]);

        $result = (new BulkSmsBdClient())->send('8801712345678', 'hello');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['http_status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://bulksmsbd.net/api/smsapi'
                && $request->method() === 'POST'
                && $request->data() === [
                    'api_key' => 'test-key',
                    'senderid' => 'TESTID',
                    'number' => '8801712345678',
                    'message' => 'hello',
                ];
        });
    }

    public function test_returns_failure_on_non_2xx(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response('Server Error', 500),
        ]);

        $result = (new BulkSmsBdClient())->send('8801712345678', 'hello');

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['http_status']);
        $this->assertSame('Server Error', $result['body']);
    }

    public function test_catches_exceptions_and_returns_failure(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL: timeout');
        });

        $result = (new BulkSmsBdClient())->send('8801712345678', 'hello');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['http_status']);
        $this->assertStringContainsString('timeout', $result['exception']);
    }

    public function test_is_configured_returns_false_when_credentials_missing(): void
    {
        config([
            'services.bulksms.api_key' => '',
            'services.bulksms.sender_id' => 'TESTID',
        ]);
        $this->assertFalse((new BulkSmsBdClient())->isConfigured());

        config(['services.bulksms.api_key' => 'k', 'services.bulksms.sender_id' => '']);
        $this->assertFalse((new BulkSmsBdClient())->isConfigured());

        config(['services.bulksms.api_key' => 'k', 'services.bulksms.sender_id' => 's']);
        $this->assertTrue((new BulkSmsBdClient())->isConfigured());
    }
}
```

- [ ] **Step 4: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/Feature/BulkSmsBdClientTest.php
```

Expected: FAIL — `Class "App\Services\Sms\BulkSmsBdClient" not found`.

- [ ] **Step 5: Implement the client**

```php
<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class BulkSmsBdClient
{
    /**
     * @return array{ok: bool, http_status: ?int, body: ?string, exception?: string}
     */
    public function send(string $phone, string $message): array
    {
        $payload = [
            'api_key' => (string) config('services.bulksms.api_key'),
            'senderid' => (string) config('services.bulksms.sender_id'),
            'number' => $phone,
            'message' => $message,
        ];

        $url = (string) config('services.bulksms.url', 'http://bulksmsbd.net/api/smsapi');

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->withOptions(['verify' => false])
                ->post($url, $payload);

            return [
                'ok' => $response->successful(),
                'http_status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'http_status' => null,
                'body' => null,
                'exception' => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        $key = config('services.bulksms.api_key');
        $sender = config('services.bulksms.sender_id');

        return is_string($key) && $key !== ''
            && is_string($sender) && $sender !== '';
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

```bash
vendor/bin/phpunit tests/Feature/BulkSmsBdClientTest.php
```

Expected: 4 tests, all PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Sms/BulkSmsBdClient.php tests/Feature/BulkSmsBdClientTest.php config/services.php .env.example
git commit -m "feat(sms): add BulkSmsBdClient + config"
```

---

## Task 5: `ReunionRegistrationFactory` for tests

**Files:**
- Create: `database/factories/ReunionRegistrationFactory.php`
- Create: `database/factories/StudentFactory.php`

- [ ] **Step 1: Write the Student factory**

```php
<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => '01712345678',
            'graduation_year' => '2015',
            'department' => 'CSE',
            'session' => '2011-12',
            'current_occupation' => null,
            'present_address' => null,
            'permanent_address' => null,
            'office_address' => null,
            'photo' => null,
        ];
    }
}
```

- [ ] **Step 2: Write the ReunionRegistration factory**

```php
<?php

namespace Database\Factories;

use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReunionRegistration>
 */
class ReunionRegistrationFactory extends Factory
{
    protected $model = ReunionRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reunion_id' => Reunion::factory(),
            'student_id' => Student::factory(),
            'tshirt_size' => 'L',
            'guest_count' => 0,
            'total_fee' => 500.00,
            'registration_status' => 'pending',
            'payment_status' => 'unpaid',
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'registration_status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'registration_status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add database/factories/StudentFactory.php database/factories/ReunionRegistrationFactory.php
git commit -m "test: add Student and ReunionRegistration factories"
```

---

## Task 6: `SendRegistrationSms` service — failing test

**Files:**
- Create: `tests/Feature/SendRegistrationSmsTest.php`
- Create: `app/Services/Sms/SendRegistrationSms.php`

- [ ] **Step 1: Write the failing integration test**

```php
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
        $registration = $this->makeRegistrationWithGuests('unpaid');
        $registration->student->update(['phone' => null]);

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

    public function test_writes_failed_log_when_provider_returns_5xx_but_sms_log_still_created(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('Server Error', 500)]);

        $registration = $this->makeRegistrationWithGuests('unpaid');
        $log = app(SendRegistrationSms::class)->send($registration, 'payment_paid');

        $this->assertSame('failed', $log->status);
        $this->assertSame(500, $log->http_status);
        $this->assertSame('Server Error', $log->provider_response);
        $this->assertNotNull($log->sent_at); // still timestamped for ordering
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/Feature/SendRegistrationSmsTest.php
```

Expected: FAIL — `Class "App\Services\Sms\SendRegistrationSms" not found`.

- [ ] **Step 3: Implement the service**

```php
<?php

namespace App\Services\Sms;

use App\Models\ReunionGuest;
use App\Models\ReunionRegistration;
use App\Models\SmsLog;

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
            'phone' => $phone ?? '',
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

        $base['phone'] = $phone;
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
        if ($registration->relationLoaded('guests')) {
            $guests = $registration->guests;
        } else {
            $guests = ReunionGuest::where('registration_id', $registration->id)->get();
        }

        $adult = $guests->where('guest_type', 'Adult')->count();
        $child = $guests->where('guest_type', 'Child')->count();

        return [$adult, $child];
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
vendor/bin/phpunit tests/Feature/SendRegistrationSmsTest.php
```

Expected: 5 tests, all PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sms/SendRegistrationSms.php tests/Feature/SendRegistrationSmsTest.php
git commit -m "feat(sms): SendRegistrationSms service with audit log"
```

---

## Task 7: Filament page hooks — failing test

**Files:**
- Create: `tests/Feature/AdminRegistrationSmsTest.php`
- Modify: `app/Filament/Resources/Registrations/Pages/ViewRegistration.php`

- [ ] **Step 1: Write the failing feature test**

The page extends `EditRecord`. Filament tests authenticate as a Filament admin via `actingAs($user)`. Use the existing `UserFactory`.

```php
<?php

namespace Tests\Feature;

use App\Models\Reunion;
use App\Models\ReunionGuest;
use App\Models\ReunionGuestFee;
use App\Models\ReunionRegistration;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminRegistrationSmsTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function registration(string $paymentStatus): ReunionRegistration
    {
        $reunion = Reunion::factory()->create(['title' => 'SWAATC Reunion 2026']);
        ReunionGuestFee::factory()->create(['reunion_id' => $reunion->id, 'guest_type' => 'Adult', 'fee' => 300.00, 'is_active' => true]);
        $student = Student::factory()->create(['phone' => '01712345678']);
        $reg = ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->id,
            'student_id' => $student->id,
            'payment_status' => $paymentStatus,
            'total_fee' => '500.00',
        ]);
        ReunionGuest::create(['registration_id' => $reg->id, 'guest_type' => 'Adult', 'fee' => '300.00']);
        return $reg->fresh(['reunion', 'student']);
    }

    public function test_admin_transitioning_to_paid_sends_confirmation_sms(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('OK', 200)]);

        $reg = $this->registration('unpaid');
        $this->actingAs($this->admin());

        $this->patch(
            \App\Filament\Resources\Registrations\RegistrationResource::getUrl('view', ['record' => $reg]),
            [
                'payment_status' => 'paid',
                'registration_status' => 'confirmed',
            ]
        )->assertSuccessful();

        Http::assertSentCount(1);
        $log = SmsLog::first();
        $this->assertNotNull($log);
        $this->assertSame('payment_paid', $log->trigger);
        $this->assertSame('sent', $log->status);
        $this->assertSame('8801712345678', $log->phone);
    }

    public function test_admin_transitioning_to_refunded_sends_cancellation_sms(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('OK', 200)]);

        $reg = $this->registration('paid');
        $this->actingAs($this->admin());

        $this->patch(
            \App\Filament\Resources\Registrations\RegistrationResource::getUrl('view', ['record' => $reg]),
            [
                'payment_status' => 'refunded',
                'registration_status' => 'cancelled',
            ]
        )->assertSuccessful();

        $this->assertSame('payment_refunded', SmsLog::first()->trigger);
        $this->assertStringContainsString('cancel', strtolower(SmsLog::first()->message));
    }

    public function test_no_sms_sent_when_resaving_unchanged_paid_status(): void
    {
        Http::fake();

        $reg = $this->registration('paid');
        $this->actingAs($this->admin());

        $this->patch(
            \App\Filament\Resources\Registrations\RegistrationResource::getUrl('view', ['record' => $reg]),
            [
                'payment_status' => 'paid',
                'registration_status' => 'confirmed',
            ]
        )->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, SmsLog::count());
    }

    public function test_no_sms_sent_when_transitioning_to_unpaid(): void
    {
        Http::fake();

        $reg = $this->registration('paid');
        $this->actingAs($this->admin());

        $this->patch(
            \App\Filament\Resources\Registrations\RegistrationResource::getUrl('view', ['record' => $reg]),
            [
                'payment_status' => 'unpaid',
                'registration_status' => 'pending',
            ]
        )->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, SmsLog::count());
    }

    public function test_save_still_succeeds_when_sms_fails(): void
    {
        Http::fake(['bulksmsbd.net/*' => Http::response('Server Error', 500)]);

        $reg = $this->registration('unpaid');
        $this->actingAs($this->admin());

        $this->patch(
            \App\Filament\Resources\Registrations\RegistrationResource::getUrl('view', ['record' => $reg]),
            [
                'payment_status' => 'paid',
                'registration_status' => 'confirmed',
            ]
        )->assertSuccessful();

        $this->assertSame('paid', $reg->fresh()->payment_status);
        $this->assertSame('failed', SmsLog::first()->status);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/Feature/AdminRegistrationSmsTest.php
```

Expected: FAIL — the tests will fail because either no HTTP send happens (current code does not send SMS) or because Filament's EditRecord save path differs from a plain PATCH. Expect failures on the asserts about `Http::assertSentCount(1)` and SMS log rows.

- [ ] **Step 3: Wire `afterSave()` and the resend action into the page**

Edit `app/Filament/Resources/Registrations/Pages/ViewRegistration.php`. Add the following imports at the top:

```php
use App\Models\SmsLog;
use App\Services\Sms\SendRegistrationSms;
use Filament\Notifications\Notification;
```

Replace the existing `getHeaderActions()` method to include the resend action:

```php
protected function getHeaderActions(): array
{
    return [
        Action::make('back')
            ->label('Back to Reunion')
            ->icon(Heroicon::ArrowLeft)
            ->color('gray')
            ->url(fn (): string => ReunionResource::getUrl('view', ['record' => $this->getRecord()->reunion])),
        Action::make('resendSms')
            ->label('Resend Confirmation SMS')
            ->icon(Heroicon::PaperAirplane)
            ->color('gray')
            ->visible(fn () => $this->getRecord()->payment_status === 'paid')
            ->requiresConfirmation()
            ->modalHeading('Resend confirmation SMS?')
            ->modalDescription(fn () => 'Send the registration-confirmed SMS again to ' . ($this->getRecord()->student?->phone ?? '(no phone on file)') . '.')
            ->action(function () {
                /** @var ReunionRegistration $record */
                $record = $this->getRecord();
                $log = app(SendRegistrationSms::class)->send(
                    $record->loadMissing(['reunion', 'student', 'guests']),
                    'manual_resend'
                );

                Notification::make()
                    ->title($log->status === 'sent' ? "SMS sent to {$log->phone}" : 'SMS send failed')
                    ->body($log->status === 'sent' ? null : $log->error_message)
                    ->status($log->status === 'sent' ? 'success' : 'warning')
                    ->send();
            }),
    ];
}
```

Also add the use statement for `ReunionRegistration` if not already imported (it isn't in this file currently):

```php
use App\Models\ReunionRegistration;
```

Add the `afterSave()` method right after `mutateFormDataBeforeSave()`:

```php
protected function afterSave(): void
{
    /** @var ReunionRegistration $record */
    $record = $this->getRecord()->loadMissing(['reunion', 'student', 'guests']);

    $new = $record->payment_status;
    $old = $record->getOriginal('payment_status');

    if ($new === $old) {
        return;
    }

    $trigger = match (true) {
        $new === 'paid' => 'payment_paid',
        $new === 'refunded' => 'payment_refunded',
        default => null,
    };

    if ($trigger === null) {
        return;
    }

    app(SendRegistrationSms::class)->send($record, $trigger);
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
vendor/bin/phpunit tests/Feature/AdminRegistrationSmsTest.php
```

Expected: 5 tests pass.

> **Troubleshooting:** if the patch endpoint returns 404 or a different route, inspect the page's URL with `php artisan route:list | grep registration` to find the right path. Filament `EditRecord` typically uses `PUT` not `PATCH`; if needed, swap `patch` for `put` in the tests.

- [ ] **Step 5: Run the full test suite to make sure nothing else regressed**

```bash
vendor/bin/phpunit
```

Expected: all previously-passing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Registrations/Pages/ViewRegistration.php tests/Feature/AdminRegistrationSmsTest.php
git commit -m "feat(filament): send SMS on payment status transition + resend action"
```

---

## Task 8: Final verification

- [ ] **Step 1: Lint touched files**

```bash
vendor/bin/pint app/Services/Sms app/Support app/Models/SmsLog.php app/Filament/Resources/Registrations/Pages/ViewRegistration.php config/services.php
```

Expected: clean exit code, or only auto-fixes.

- [ ] **Step 2: Run the full test suite**

```bash
vendor/bin/phpunit
```

Expected: all green.

- [ ] **Step 3: Commit any pint auto-fixes**

```bash
git status
git add -A
git commit -m "style: pint formatting" --allow-empty
```

---

## Notes for the executor

- Always run the test class **before** and **after** each change to confirm the red→green loop.
- The `afterSave()` call uses `loadMissing()` so eager-loading the relation is cheap even when the Filament page already loaded it.
- `ReunionRegistration::getOriginal('payment_status')` works correctly only **after** the record has been persisted. Filament's EditRecord save flow runs `save()` then `afterSave()` — by which time `getOriginal()` reflects the *prior* persisted value, so the transition guard is correct.
- For the `HTTP::fake` exception test in Task 4, `ConnectionException` is a valid Laravel HTTP exception and the closure form is supported by `Http::fake()`.

## Self-Review Checklist

- [x] Spec coverage: every requirement in the spec has at least one task. (Phone normalization → Task 3, credentials/env → Task 4, audit table → Tasks 1-2, send on paid → Tasks 6-7, send on refunded → Tasks 6-7, no-send guards → Tasks 6-7, resend action → Task 7, Save succeeds on SMS failure → Task 7.)
- [x] No placeholders: every code block shows the exact code; no "TBD".
- [x] Type consistency: `SendRegistrationSms::send(ReunionRegistration, string): SmsLog` used uniformly across Tasks 6 and 7. `BulkSmsBdClient::send(string, string): array` likewise. `BangladeshPhone::toE164(string): ?string`.
- [x] File paths absolute and exact.
