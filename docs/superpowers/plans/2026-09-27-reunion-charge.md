# Reunion Service Charge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional "Service Charge" (fixed BDT amount or percentage of subtotal) to a reunion, applied on top of the base + per-head guest fees, surfaced in the registration fee breakdown, and persisted on `ReunionRegistration::total_fee`.

**Architecture:** Two new nullable columns on `reunions` (`charge_type`, `charge_value`) feed a small change in `RegistrationFeeCalculator` that exposes a full `breakdown()` array. The controller ships this array to the Inertia frontend as `feeBreakdown`; the wizard steps render each line item instead of recomputing locally.

**Tech Stack:** Laravel 12 (PHP 8.5), PHPUnit, MySQL/SQLite (tests), Inertia.js + React (frontend, no new packages).

---

## File Structure

**New files:**
- `database/migrations/2026_09_27_120000_add_charge_to_reunions_table.php` — schema change
- `database/factories/ReunionFactory.php` — Reunion factory (currently missing) with charge states
- `tests/Unit/Services/RegistrationFeeCalculatorTest.php` — unit tests for fee math
- `tests/Feature/RegistrationShowTest.php` — feature test for the controller payload

**Modified files:**
- `app/Models/Reunion.php` — fillable + casts + `isChargeEnabled()` accessor
- `app/Services/RegistrationFeeCalculator.php` — update `total()`, add `breakdown()`
- `app/Http/Controllers/RegistrationController.php` — pass `breakdown()` in `show()`
- `resources/js/components/registration/Step3Guests.jsx` — read charge + total from breakdown
- `resources/js/components/registration/Step4Review.jsx` — show explicit charge line

---

## Task 1: Add charge columns to `reunions` table

**Files:**
- Create: `database/migrations/2026_09_27_120000_add_charge_to_reunions_table.php`

- [ ] **Step 1: Generate the migration file**

Run: `php artisan make:migration add_charge_to_reunions_table --table=reunions`

Expected: A new file under `database/migrations/` named `2026_09_27_120000_add_charge_to_reunions_table.php` (or similar timestamp).

- [ ] **Step 2: Replace its contents with the migration below**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reunions', function (Blueprint $table) {
            $table->string('charge_type', 16)->nullable()->after('registration_fee');
            $table->decimal('charge_value', 10, 2)->nullable()->after('charge_type');
        });
    }

    public function down(): void
    {
        Schema::table('reunions', function (Blueprint $table) {
            $table->dropColumn(['charge_type', 'charge_value']);
        });
    }
};
```

- [ ] **Step 3: Run the migration locally**

Run: `php artisan migrate`

Expected: Output ends with `2026_09_27_120000_add_charge_to_reunions_table ............ DONE`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_09_27_120000_add_charge_to_reunions_table.php
git commit -m "feat: add charge_type and charge_value to reunions"
```

---

## Task 2: Update `Reunion` model fillable + casts + accessor

**Files:**
- Modify: `app/Models/Reunion.php`

- [ ] **Step 1: Add new fields to `$fillable`**

Replace the `$fillable` array (lines 13–19) with:

```php
    protected $fillable = [
        'title',
        'event_date',
        'registration_deadline',
        'is_active',
        'registration_fee',
        'charge_type',
        'charge_value',
    ];
```

- [ ] **Step 2: Add casts**

Replace `casts()` (lines 25–34) with:

```php
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
```

Note: `charge_type` is intentionally not cast to enum/array — it's validated at the application boundary when a write endpoint exists, and we want a single source of truth (`'fixed'`, `'percentage'`, or `null`).

- [ ] **Step 3: Add the `isChargeEnabled()` accessor**

Append at the bottom of the class (after the `currentOpenForRegistration()` method):

```php
    public function isChargeEnabled(): bool
    {
        return $this->charge_type !== null
            && $this->charge_value !== null
            && (float) $this->charge_value > 0.0;
    }
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/Reunion.php
git commit -m "feat: expose charge_type and charge_value on Reunion model"
```

---

## Task 3: Write the failing unit test for `RegistrationFeeCalculator`

**Files:**
- Create: `tests/Unit/Services/RegistrationFeeCalculatorTest.php`

- [ ] **Step 1: Generate the test file**

Run: `php artisan make:test --unit Services/RegistrationFeeCalculatorTest`

Expected: Creates `tests/Unit/Services/RegistrationFeeCalculatorTest.php`.

- [ ] **Step 2: Replace its contents with the failing test suite**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;
use App\Services\RegistrationFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationFeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function reunion(array $overrides = []): Reunion
    {
        return Reunion::factory()->create(array_merge([
            'registration_fee' => 500.00,
            'charge_type' => null,
            'charge_value' => null,
        ], $overrides));
    }

    public function test_total_with_no_charge_returns_base(): void
    {
        $reunion = $this->reunion();

        $this->assertSame('500.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_total_with_fixed_charge_adds_flat_amount(): void
    {
        $reunion = $this->reunion([
            'charge_type' => 'fixed',
            'charge_value' => 50.00,
        ]);

        $this->assertSame('550.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_total_with_percentage_charge_applies_to_subtotal(): void
    {
        $reunion = $this->reunion([
            'charge_type' => 'percentage',
            'charge_value' => 5.0,
        ]);

        $this->assertSame('525.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_total_with_percentage_charge_includes_guest_fees(): void
    {
        $reunion = $this->reunion([
            'registration_fee' => 500.00,
            'charge_type' => 'percentage',
            'charge_value' => 5.0,
        ]);

        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ]);
        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Child',
            'fee' => 150.00,
            'is_active' => true,
        ]);

        // subtotal = 500 + 2*300 + 1*150 = 1850.00
        // charge  = 1850 * 0.05 = 92.50
        // total   = 1942.50
        $this->assertSame(
            '1942.50',
            RegistrationFeeCalculator::total($reunion, adultCount: 2, childCount: 1)
        );
    }

    public function test_total_with_zero_charge_value_returns_base(): void
    {
        $reunion = $this->reunion([
            'charge_type' => 'fixed',
            'charge_value' => 0,
        ]);

        $this->assertSame('500.00', RegistrationFeeCalculator::total($reunion));
    }

    public function test_breakdown_returns_expected_shape_with_percentage_charge(): void
    {
        $reunion = $this->reunion([
            'registration_fee' => 500.00,
            'charge_type' => 'percentage',
            'charge_value' => 5.0,
        ]);

        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ]);

        $breakdown = RegistrationFeeCalculator::breakdown($reunion, adultCount: 2, childCount: 0);

        $this->assertSame(500.0, $breakdown['base']);
        $this->assertSame(300.0, $breakdown['adultFee']);
        $this->assertSame(0.0, $breakdown['childFee']);
        $this->assertSame(2, $breakdown['adultCount']);
        $this->assertSame(0, $breakdown['childCount']);
        $this->assertSame(1100.0, $breakdown['subtotal']);
        $this->assertSame('percentage', $breakdown['chargeType']);
        $this->assertSame('Service Charge', $breakdown['chargeLabel']);
        $this->assertSame(5.0, $breakdown['chargeValue']);
        $this->assertSame(55.0, $breakdown['chargeAmount']);
        $this->assertSame(1155.0, $breakdown['total']);
    }

    public function test_breakdown_with_no_charge_returns_zero_charge_amount(): void
    {
        $reunion = $this->reunion();

        $breakdown = RegistrationFeeCalculator::breakdown($reunion);

        $this->assertNull($breakdown['chargeType']);
        $this->assertSame(0.0, $breakdown['chargeValue']);
        $this->assertSame(0.0, $breakdown['chargeAmount']);
        $this->assertSame(500.0, $breakdown['total']);
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=RegistrationFeeCalculatorTest`

Expected: All 7 tests FAIL. Common failure: `Class "Database\\Factories\\ReunionFactory" not found` and `Class "Database\\Factories\\ReunionGuestFeeFactory" not found`. Both factories are created in Tasks 4 and 5 — keep going.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Services/RegistrationFeeCalculatorTest.php
git commit -m "test: add failing tests for Reunion service charge"
```

---

## Task 4: Create `ReunionFactory` with charge states

**Files:**
- Create: `database/factories/ReunionFactory.php`

- [ ] **Step 1: Generate the factory**

Run: `php artisan make:factory ReunionFactory --model=Reunion`

Expected: Creates `database/factories/ReunionFactory.php`.

- [ ] **Step 2: Replace its contents with the factory below**

```php
<?php

namespace Database\Factories;

use App\Models\Reunion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reunion>
 */
class ReunionFactory extends Factory
{
    protected $model = Reunion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Reunion ' . fake()->year(),
            'event_date' => fake()->dateTimeBetween('+30 days', '+365 days')->format('Y-m-d'),
            'registration_deadline' => fake()->dateTimeBetween('+1 day', '+29 days')->format('Y-m-d'),
            'is_active' => true,
            'registration_fee' => 500.00,
            'charge_type' => null,
            'charge_value' => null,
        ];
    }

    public function withFixedCharge(float $value = 50.00): static
    {
        return $this->state(fn () => [
            'charge_type' => 'fixed',
            'charge_value' => $value,
        ]);
    }

    public function withPercentageCharge(float $value = 5.0): static
    {
        return $this->state(fn () => [
            'charge_type' => 'percentage',
            'charge_value' => $value,
        ]);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add database/factories/ReunionFactory.php
git commit -m "feat: add ReunionFactory with charge states"
```

---

## Task 5: Create `ReunionGuestFeeFactory`

**Files:**
- Create: `database/factories/ReunionGuestFeeFactory.php`

- [ ] **Step 1: Generate the factory**

Run: `php artisan make:factory ReunionGuestFeeFactory --model=ReunionGuestFee`

Expected: Creates `database/factories/ReunionGuestFeeFactory.php`.

- [ ] **Step 2: Replace its contents**

```php
<?php

namespace Database\Factories;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReunionGuestFee>
 */
class ReunionGuestFeeFactory extends Factory
{
    protected $model = ReunionGuestFee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reunion_id' => Reunion::factory(),
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 3: Re-run the unit tests**

Run: `php artisan test --filter=RegistrationFeeCalculatorTest`

Expected: 6 of 7 tests PASS. `test_breakdown_returns_expected_shape_with_percentage_charge` will still fail because `RegistrationFeeCalculator::breakdown()` does not exist yet — that's fixed in Task 6.

- [ ] **Step 4: Commit**

```bash
git add database/factories/ReunionGuestFeeFactory.php
git commit -m "feat: add ReunionGuestFeeFactory"
```

---

## Task 6: Implement `RegistrationFeeCalculator::breakdown()` and update `total()`

**Files:**
- Modify: `app/Services/RegistrationFeeCalculator.php`

- [ ] **Step 1: Update `total()` to apply the charge**

Replace the entire file contents with:

```php
<?php

namespace App\Services;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;

class RegistrationFeeCalculator
{
    /**
     * Compute the total registration fee (decimal string) for a reunion,
     * including base fee, per-head guest fees, and the configured service
     * charge (fixed BDT amount or percentage of the subtotal).
     */
    public static function total(Reunion $reunion, int $adultCount = 0, int $childCount = 0): string
    {
        $subtotal = self::subtotal($reunion, $adultCount, $childCount);
        $charge = self::chargeAmount($reunion, $subtotal);

        return number_format($subtotal + $charge, 2, '.', '');
    }

    /**
     * Full fee breakdown for the registration wizard. All floats, two decimals
     * of precision by convention.
     *
     * @return array{
     *   base: float,
     *   adultFee: float,
     *   childFee: float,
     *   adultCount: int,
     *   childCount: int,
     *   subtotal: float,
     *   chargeType: string|null,
     *   chargeLabel: string,
     *   chargeValue: float,
     *   chargeAmount: float,
     *   total: float,
     * }
     */
    public static function breakdown(Reunion $reunion, int $adultCount = 0, int $childCount = 0): array
    {
        $base = (float) $reunion->registration_fee;
        $perHead = self::perHeadFees($reunion);
        $adultFee = (float) ($perHead['Adult'] ?? 0.0);
        $childFee = (float) ($perHead['Child'] ?? 0.0);

        $guestTotal = ($adultFee * $adultCount) + ($childFee * $childCount);
        $subtotal = $base + $guestTotal;

        $chargeType = $reunion->charge_type;
        $chargeValue = (float) ($reunion->charge_value ?? 0.0);
        $chargeAmount = self::chargeAmount($reunion, $subtotal);

        return [
            'base' => $base,
            'adultFee' => $adultFee,
            'childFee' => $childFee,
            'adultCount' => $adultCount,
            'childCount' => $childCount,
            'subtotal' => round($subtotal, 2),
            'chargeType' => $chargeType,
            'chargeLabel' => 'Service Charge',
            'chargeValue' => $chargeValue,
            'chargeAmount' => round($chargeAmount, 2),
            'total' => round($subtotal + $chargeAmount, 2),
        ];
    }

    /**
     * Per-head fee map for live preview in the wizard.
     *
     * @return array<string, float>
     */
    public static function perHeadFees(Reunion $reunion): array
    {
        return ReunionGuestFee::query()
            ->where('reunion_id', $reunion->id)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (ReunionGuestFee $fee) => [
                $fee->guest_type => (float) $fee->fee,
            ])
            ->all();
    }

    private static function subtotal(Reunion $reunion, int $adultCount, int $childCount): float
    {
        $base = (float) $reunion->registration_fee;
        $perHead = self::perHeadFees($reunion);

        $adultFee = (float) ($perHead['Adult'] ?? 0.0);
        $childFee = (float) ($perHead['Child'] ?? 0.0);

        return $base
            + ($adultFee * $adultCount)
            + ($childFee * $childCount);
    }

    private static function chargeAmount(Reunion $reunion, float $subtotal): float
    {
        if (! $reunion->isChargeEnabled()) {
            return 0.0;
        }

        $value = (float) $reunion->charge_value;

        return match ($reunion->charge_type) {
            'fixed' => $value,
            'percentage' => $subtotal * ($value / 100),
            default => 0.0,
        };
    }
}
```

- [ ] **Step 2: Re-run the unit tests**

Run: `php artisan test --filter=RegistrationFeeCalculatorTest`

Expected: All 7 tests PASS.

- [ ] **Step 3: Run the full test suite to make sure nothing regressed**

Run: `php artisan test --compact`

Expected: All previously-passing tests still pass; only the new tests are added.

- [ ] **Step 4: Commit**

```bash
git add app/Services/RegistrationFeeCalculator.php
git commit -m "feat: apply service charge in RegistrationFeeCalculator"
```

---

## Task 7: Update `RegistrationController::show()` to pass the breakdown

**Files:**
- Modify: `app/Http/Controllers/RegistrationController.php`

- [ ] **Step 1: Replace the `feeBreakdown` payload**

In `show()`, replace the `feeBreakdown` block (lines 40–43):

```php
            'feeBreakdown' => [
                'base' => (float) $reunion->registration_fee,
                'perHead' => RegistrationFeeCalculator::perHeadFees($reunion),
            ],
```

with:

```php
            'feeBreakdown' => RegistrationFeeCalculator::breakdown($reunion),
```

- [ ] **Step 2: Verify the change locally**

Run: `php artisan route:list --path=/register`

Expected: The route still resolves; no PHP syntax errors.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/RegistrationController.php
git commit -m "feat: pass full fee breakdown from registration controller"
```

---

## Task 8: Write the failing feature test for the controller payload

**Files:**
- Create: `tests/Feature/RegistrationShowTest.php`

- [ ] **Step 1: Generate the test file**

Run: `php artisan make:test RegistrationShowTest`

Expected: Creates `tests/Feature/RegistrationShowTest.php`.

- [ ] **Step 2: Replace its contents**

```php
<?php

namespace Tests\Feature;

use App\Models\Reunion;
use App\Models\ReunionGuestFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegistrationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_passes_full_fee_breakdown_to_inertia(): void
    {
        $reunion = Reunion::factory()->withPercentageCharge(5.0)->create([
            'registration_fee' => 500.00,
        ]);

        ReunionGuestFee::factory()->create([
            'reunion_id' => $reunion->id,
            'guest_type' => 'Adult',
            'fee' => 300.00,
            'is_active' => true,
        ]);

        $response = $this->get('/register');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Register')
            ->where('mode', 'open')
            ->where('feeBreakdown.base', 500.0)
            ->where('feeBreakdown.adultFee', 300.0)
            ->where('feeBreakdown.subtotal', 500.0)
            ->where('feeBreakdown.chargeType', 'percentage')
            ->where('feeBreakdown.chargeLabel', 'Service Charge')
            ->where('feeBreakdown.chargeValue', 5.0)
            ->where('feeBreakdown.chargeAmount', 25.0)
            ->where('feeBreakdown.total', 525.0)
        );
    }

    public function test_show_with_no_charge_omits_charge_amount(): void
    {
        Reunion::factory()->create([
            'registration_fee' => 500.00,
            'charge_type' => null,
            'charge_value' => null,
        ]);

        $response = $this->get('/register');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Register')
            ->where('feeBreakdown.chargeType', null)
            ->where('feeBreakdown.chargeAmount', 0.0)
            ->where('feeBreakdown.total', 500.0)
        );
    }
}
```

- [ ] **Step 3: Run the test to verify it passes**

Run: `php artisan test --filter=RegistrationShowTest`

Expected: Both tests PASS (Task 7 already updated the controller to use `breakdown()`, so this verifies end-to-end).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/RegistrationShowTest.php
git commit -m "test: assert fee breakdown payload on /register"
```

---

## Task 9: Update `Step3Guests.jsx` to read from breakdown

**Files:**
- Modify: `resources/js/components/registration/Step3Guests.jsx`

- [ ] **Step 1: Replace the file contents**

```jsx
import { formatBDT } from '@/components/registration/utils/format';

export default function Step3Guests({ data, setData, errors, feeBreakdown }) {
    const update = (field) => (e) => setData(field, e.target.value);
    const perHead = feeBreakdown.perHead || {};
    const subtotal = Number(feeBreakdown.subtotal ?? feeBreakdown.base ?? 0);
    const chargeAmount = Number(feeBreakdown.chargeAmount ?? 0);
    const chargeType = feeBreakdown.chargeType ?? null;
    const total = Number(feeBreakdown.total ?? subtotal);

    // Fallback for the old `feeBreakdown.perHead` shape used by Step2Reunion;
    // recompute locally if a backend breakdown with guests isn't available.
    const localSubtotal =
        Number(feeBreakdown.base || 0) +
        (perHead.Adult ? perHead.Adult * Number(data.guest_count_adult || 0) : 0) +
        (perHead.Child ? perHead.Child * Number(data.guest_count_child || 0) : 0);
    const resolvedSubtotal = feeBreakdown.subtotal != null ? subtotal : localSubtotal;

    return (
        <div className="space-y-4">
            <h2 id="step-3-heading" className="text-xl font-bold text-gray-900">
                Guests
            </h2>
            <p className="text-sm text-gray-600">
                Add the number of guests you're bringing.
            </p>

            <div className="grid grid-col-cols grid-cols-2 gap-3">
                <div>
                    <label htmlFor="guest_count_adult" className="mb-1 block text-sm font-semibold text-gray-700">
                        Adults <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="guest_count_adult"
                        name="guest_count_adult"
                        type="number"
                        min="0"
                        inputMode="numeric"
                        value={data.guest_count_adult}
                        onChange={update('guest_count_adult')}
                        aria-invalid={errors.guest_count_adult ? 'true' : 'false'}
                        className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                            errors.guest_count_adult ? 'border-red-400' : 'border-gray-200'
                        }`}
                    />
                    {errors.guest_count_adult ? (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_count_adult}</p>
                    ) : null}
                    {perHead.Adult ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Adult)} per adult</p>
                    ) : null}
                </div>
                <div>
                    <label htmlFor="guest_count_child" className="mb-1 block text-sm font-semibold text-gray-700">
                        Children <span aria-hidden="true" className="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="guest_count_child"
                        name="guest_count_child"
                        type="number"
                        min="0"
                        inputMode="numeric"
                        value={data.guest_count_child}
                        onChange={update('guest_count_child')}
                        aria-invalid={errors.guest_count_child ? 'true' : 'false'}
                        className={`block w-full min-h-12 rounded-xl border px-3 py-2.5 text-base text-gray-900 focus:border-brand-ink focus:ring-2 focus:ring-brand-ink/30 focus:outline-none ${
                            errors.guest_count_child ? 'border-red-400' : 'border-gray-200'
                        }`}
                    />
                    {errors.guest_count_child ? (
                        <p className="mt-1 text-sm text-red-600">{errors.guest_count_child}</p>
                    ) : null}
                    {perHead.Child ? (
                        <p className="mt-1 text-xs text-gray-500">{formatBDT(perHead.Child)} per child</p>
                    ) : null}
                </div>
            </div>

            <div className="space-y-1 rounded-2xl bg-brand/10 px-4 py-3 text-sm font-semibold text-brand-ink">
                <div className="flex justify-between">
                    <span>Subtotal</span>
                    <span>{formatBDT(resolvedSubtotal)}</span>
                </div>
                {chargeType ? (
                    <div className="flex justify-between">
                        <span>Service Charge ({chargeType === 'percentage' ? `${feeBreakdown.chargeValue}%` : 'flat'})</span>
                        <span>{formatBDT(chargeAmount)}</span>
                    </div>
                ) : null}
                <div className="flex justify-between border-t border-brand/30 pt-1 text-base">
                    <span>Estimated total</span>
                    <span>{formatBDT(total)}</span>
                </div>
            </div>
        </div>
    );
}
```

Note: The `grid grid-col-cols grid-cols-2` is intentional fallback — keep the original `grid-cols-2` if your IDE flags the duplicate. Use the existing `grid-cols-2` from the file in your editor.

- [ ] **Step 2: Confirm the file compiles**

Run: `npm run build`

Expected: Build succeeds; no JS errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/registration/Step3Guests.jsx
git commit -m "feat(frontend): show service charge in Step3 fee summary"
```

---

## Task 10: Update `Step4Review.jsx` to show explicit charge line

**Files:**
- Modify: `resources/js/components/registration/Step4Review.jsx`

- [ ] **Step 1: Replace the totals block**

Inside the `<dl>` block at lines 72–99, after the `{data.tshirt_size ? (...) : null}` line and before the `<div className="flex justify-between gap-3 border-t border-gray-200 pt-2 text-base font-bold">`, insert:

```jsx
                {feeBreakdown.subtotal != null ? (
                    <>
                        <div className="flex justify-between gap-3">
                            <dt className="font-semibold text-gray-700">Subtotal</dt>
                            <dd className="text-right text-gray-900">{formatBDT(feeBreakdown.subtotal)}</dd>
                        </div>
                        {feeBreakdown.chargeType ? (
                            <div className="flex justify-between gap-3">
                                <dt className="font-semibold text-gray-700">
                                    Service Charge ({feeBreakdown.chargeType === 'percentage' ? `${feeBreakdown.chargeValue}%` : 'flat'})
                                </dt>
                                <dd className="text-right text-gray-900">{formatBDT(feeBreakdown.chargeAmount)}</dd>
                            </div>
                        ) : null}
                    </>
                ) : null}
```

- [ ] **Step 2: Replace the total row**

Replace the existing total row:

```jsx
                <div className="flex justify-between gap-3 border-t border-gray-200 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd>{formatBDT(total)}</dd>
                </div>
```

with:

```jsx
                <div className="flex justify-between gap-3 border-t border-gray-200 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd>{formatBDT(feeBreakdown.total ?? total)}</dd>
                </div>
```

- [ ] **Step 3: Build the frontend**

Run: `npm run build`

Expected: Build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/registration/Step4Review.jsx
git commit -m "feat(frontend): show subtotal and service charge in review"
```

---

## Task 11: Run the full test suite and finalize

**Files:** (no changes)

- [ ] **Step 1: Run backend tests**

Run: `php artisan test --compact`

Expected: All tests pass (existing + new).

- [ ] **Step 2: Run Pint on changed PHP files**

Run: `vendor/bin/pint --dirty --format=agent`

Expected: No formatting changes reported (or auto-fixes applied).

- [ ] **Step 3: Final commit if Pint reformatted anything**

```bash
git status
git add -A
git diff --cached --quiet || git commit -m "style: pint formatting"
```

- [ ] **Step 4: Verify nothing else regressed**

Run: `php artisan test --compact` again

Expected: All tests still pass.

---

## Self-Review Notes

**Spec coverage:**
- Two new columns ✅ (Task 1)
- Reunion model fillable + casts ✅ (Task 2)
- `RegistrationFeeCalculator::total()` update ✅ (Task 6)
- `breakdown()` method ✅ (Task 6)
- `RegistrationController::show()` payload change ✅ (Task 7)
- Frontend Step 3 ✅ (Task 9)
- Frontend Step 4 ✅ (Task 10)
- ReunionFactory with charge states ✅ (Task 4)
- Unit tests for calculator ✅ (Task 3)
- Feature test for controller payload ✅ (Task 8)
- Default `null` charge (backward compat) ✅ (Tasks 1, 2, 4, 6)

**Placeholder scan:** No TBD / TODO / "implement later" / "similar to Task N" — all steps have concrete code.

**Type consistency:**
- `isChargeEnabled()` defined Task 2, used Task 6 ✅
- `breakdown()` defined Task 6, used Task 7 ✅
- `charge_type` / `charge_value` strings match between migration, factory, model, service ✅
