# Reunion Service Charge — Design

**Date:** 2026-09-27
**Status:** Approved (pending user spec review)
**Scope:** Data model, fee calculation, frontend breakdown display, factory, tests.

## Goal

Allow a reunion to apply an optional **Service Charge** on top of the registration subtotal. The charge is either **fixed** (a flat BDT amount) or **percentage** (a percent of the subtotal). The charge is applied to the **total registration amount** — i.e., `base_fee + per-head guest fees` — and is included in `ReunionRegistration::total_fee` at create time.

## Decisions (confirmed with user)

1. **Label**: "Service Charge" — used verbatim on the frontend breakdown.
2. **Model**: Single charge per reunion (no stacking multiple charges).
3. **Scope**: Data model, calculation, factory, tests — **no admin UI** in this change. Reunion rows can be set up via factory, seeder, or tinker.

## Data Model

### Migration: `add_charge_to_reunions_table`

Add two nullable columns to the `reunions` table:

| Column | Type | Default | Notes |
|--------|------|---------|-------|
| `charge_type` | `string` length 16 | `null` | One of: `null`, `'fixed'`, `'percentage'` |
| `charge_value` | `decimal(10,2)` | `null` | BDT for fixed; 0–100 for percentage |

The model will use an `AsEnum` / cast list for `charge_type` to constrain values.

A check constraint is **not** added (SQLite + MySQL portability concerns in tests). Validation lives in the model casts and request validators when those exist later.

### `Reunion` model

- Add `charge_type`, `charge_value` to `$fillable`.
- Cast `charge_type` as `string` (allow `null`, `'fixed'`, `'percentage'`).
- Cast `charge_value` as `decimal:2`.
- Add helper accessor `isChargeEnabled(): bool` returning `true` when `charge_type !== null && charge_value > 0`.

## Service Layer

### `RegistrationFeeCalculator::total(Reunion, int, int): string`

Updated to apply charge on top of subtotal:

```
subtotal = base_fee + (adult_count × adult_fee) + (child_count × child_fee)
charge =
    charge_type == 'fixed'      → charge_value
    charge_type == 'percentage' → subtotal × (charge_value / 100)
    otherwise                   → 0
return number_format(subtotal + charge, 2, '.', '')
```

### `RegistrationFeeCalculator::breakdown(Reunion, int, int): array`

New method returning the full breakdown for the frontend:

```php
[
    'base'         => 500.00,    // base fee (float)
    'adultFee'     => 300.00,    // per-adult head fee (float, 0 if not configured)
    'childFee'     => 150.00,    // per-child head fee (float, 0 if not configured)
    'adultCount'   => 2,
    'childCount'   => 1,
    'subtotal'     => 1850.00,   // base + guest totals (float)
    'chargeType'   => 'percentage',  // null|'fixed'|'percentage'
    'chargeLabel'  => 'Service Charge', // constant string
    'chargeValue'  => 5.0,       // raw charge value (float)
    'chargeAmount' => 92.50,     // computed charge (float)
    'total'        => 1942.50,   // subtotal + charge (float)
]
```

This makes the frontend display trivial — show each line item, no recomputation.

### `RegistrationFeeCalculator::perHeadFees(Reunion): array`

**Unchanged** — still used to seed default per-head fees for tests/seeders. The frontend Step 3 / Step 4 will switch to using `breakdown()` instead.

## Controller

### `RegistrationController::show()`

Pass an extended `feeBreakdown` payload to the Inertia render using the new `breakdown()` method. The shape changes from:

```js
feeBreakdown: { base: float, perHead: { Adult, Child } }
```

to:

```js
feeBreakdown: { /* full breakdown() shape above */ }
```

This is a breaking change for the frontend, but Step 3 and Step 4 are updated in the same change to use the new fields.

## Frontend Changes

### `Step3Guests.jsx`

Replace local total computation with `feeBreakdown.subtotal`, `feeBreakdown.chargeAmount`, and `feeBreakdown.total`. Add a line below the existing per-head hints showing the running subtotal and (if present) the service charge.

### `Step4Review.jsx`

Add explicit lines:
- Subtotal
- Service Charge (with type/value caption, e.g., `"5%"` or `"BDT 50.00"`) — only when `chargeType !== null`
- **Total** (already present; switched to `feeBreakdown.total`)

### `Step2Reunion.jsx`

Unchanged in structure — the existing base fee display is fine. The breakdown object shape change is backwards compatible at this step because only `feeBreakdown.base` and `feeBreakdown.perHead` are read, and we keep both fields populated.

## Factory

Create `database/factories/ReunionFactory.php` (missing today) with states:

- `definition()` → sensible defaults: `charge_type = null`, `charge_value = null`
- `withFixedCharge(float $value = 50.00)` → `charge_type = 'fixed'`, `charge_value = $value`
- `withPercentageCharge(float $value = 5.0)` → `charge_type = 'percentage'`, `charge_value = $value`

## Tests

Add `tests/Unit/Services/RegistrationFeeCalculatorTest.php`:

| Case | Setup | Expected |
|------|-------|----------|
| No charge (default) | base 500, no guests, `charge_type = null` | total = `500.00` |
| Fixed charge | base 500, `charge_type = 'fixed'`, `charge_value = 50` | total = `550.00` |
| Percentage charge | base 500, `charge_type = 'percentage'`, `charge_value = 5` | total = `525.00` |
| Percentage on subtotal incl. guests | base 500, adult 2 × 300, child 1 × 150, 5% | subtotal = 1850, charge = 92.50, total = `1942.50` |
| Zero charge_value with type set | `charge_type = 'fixed'`, `charge_value = 0` | total = base (no charge) |
| Charge value rounding | base 333.33, `charge_type = 'percentage'`, `charge_value = 7` | charge ≈ 23.33, total ≈ `356.66` (validates decimal precision) |

Add `tests/Feature/RegistrationShowTest.php` covering `RegistrationController::show()` returning the extended `feeBreakdown` shape including the charge fields.

## Files Touched

| File | Change |
|------|--------|
| `database/migrations/*_add_charge_to_reunions_table.php` | New — adds `charge_type`, `charge_value` |
| `app/Models/Reunion.php` | Add fillable + casts + `isChargeEnabled()` |
| `app/Services/RegistrationFeeCalculator.php` | Update `total()`; add `breakdown()` |
| `app/Http/Controllers/RegistrationController.php` | Use `breakdown()` in `show()` |
| `resources/js/components/registration/Step3Guests.jsx` | Show charge + total from breakdown |
| `resources/js/components/registration/Step4Review.jsx` | Show charge line + total from breakdown |
| `database/factories/ReunionFactory.php` | New — Reunion factory with charge states |
| `tests/Unit/Services/RegistrationFeeCalculatorTest.php` | New — fee math tests |
| `tests/Feature/RegistrationShowTest.php` | New — controller payload tests |

## Backward Compatibility

- Existing `reunions` rows have `charge_type = null` and `charge_value = null` after migration → no charge applied. Calculation identical to today.
- Frontend `feeBreakdown` shape changes, but the only consumer is `Step3Guests.jsx` and `Step4Review.jsx` which are updated in the same change. `Step2Reunion.jsx` continues to work because `base` and `perHead` are still populated.

## Out of Scope (deferred)

- Admin UI to edit charge on existing reunions (user opted out)
- Stacking multiple charges
- Validation request class for `charge_type` / `charge_value` (no edit endpoint exists yet)
- Storing the resolved `charge_amount` on `ReunionRegistration` (computed at create time from `total_fee` and the snapshot of `subtotal + charge`)
