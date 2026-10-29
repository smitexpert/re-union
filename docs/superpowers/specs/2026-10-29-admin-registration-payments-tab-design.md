# Design — Payments Tab & Transaction Search on the Admin Registration Page

Date: 2026-10-29
Status: Approved (pending)
Related surface: admin Filament panel, `/admin/registrations/{record}` view page

## Goal

On the admin registration page, present a registration's payment history as a proper
**Payments tab** next to the registration details, and give the admin a **search
system** that filters those payments by **transaction ID** and by the **mobile number
the money was sent from** (`send_from_mobile_number`).

Both fields already exist on the `payments` table. Nothing new is stored; the feature
is purely about presenting and querying what is already recorded by the public
payment form at `/payments/{registration_code}`.

## Current state

| Surface | Today |
|---|---|
| `/admin/reunions/{id}` | Relation managers render as **tabs** — "Registrations", "Guest Fees". |
| `/admin/registrations/{id}` | The `PaymentsRelationManager` renders as a **bare block below the form**. No tab bar. |

The asymmetry is a Filament behaviour, not a config mistake.
`HasRelationManagers::getRelationManagersContentComponent()` (vendor
`filament/filament/src/Resources/Pages/Concerns/HasRelationManagers.php:134`) wraps
relation managers in a `Tabs` schema **only when the page has more than one manager**:

```php
if ((count($managers) > 1) || $hasCombinedRelationManagerTabsWithContent) {
    // ... tabs
}
// otherwise: a single bare Livewire component, no tab bar
```

`ReunionResource` declares two managers, so it gets a tab bar.
`RegistrationResource` declares one (`PaymentsRelationManager`), so it does not.

## Page structure

Switch `ViewRegistration` to Filament's **combined content + relation-manager tabs**
mode. The page then renders one tab bar containing the page content and every
relation manager.

`app/Filament/Resources/Registrations/Pages/ViewRegistration.php`:

```php
public function hasCombinedRelationManagerTabsWithContent(): bool
{
    return true;
}

public function getContentTabLabel(): ?string
{
    return 'Registration';
}

public function getContentTabPosition(): ?ContentTabPosition
{
    return ContentTabPosition::Before;
}
```

Result:

```
┌─ Registration ──┬─ Payments ─┐
│ Student          │ Transaction ID [______] Send From [______]      │
│ Registration    │ Method [▾] Status [▾]        [Apply] [Reset]     │
│ Guests          │ ─────────────────────────────────────────────── │
│ Update Status   │ Amount  Method  Status  Transaction  Send From ⋮│
│                 │ 1000    bKash   Pending 34343FD     017…       ⋮ │
└──────────────────┴───────────────────────────────────────────────┘
```

Decisions:

- **Tab order** — `ContentTabPosition::Before` puts the content tab first and the
  relation-manager tab second. The page still **lands on "Registration"**, matching
  today's behaviour; nothing about the existing review-and-update workflow changes.
- **Content tab label** — "Registration" rather than Filament's default "Edit",
  because the tab holds registration details, not just an edit form.
- **Tab label** — "Payments", derived by Filament from the `payments` relationship
  via `getRelationshipTitle()`.
- **Deep links** — the active relation manager lives in the `?relation=` query string
  (`#[Url(as: 'relation')] public ?string $activeRelationManager`), so a link to a
  specific tab can be shared.
- **No form changes** — `EditRecord::getContentTabComponent()` already places the
  form inside the content tab, so the form schema, the `data.*` state path, the save
  button, `mutateFormDataBeforeSave()` and `afterSave()` (SMS on status change) are
  all unaffected.

## The search system

`app/Filament/Resources/Registrations/RelationManagers/PaymentsRelationManager.php`

### Filter layout

The table's filter layout changes from the default `FiltersLayout::Dropdown` to
`FiltersLayout::AboveContent`, so the search panel is **always visible above the
table** instead of hidden behind a "Filters" button. The existing `payment_method`
and `status` filters move into the same panel, giving one place for all narrowing.

### The search filter

A custom table `Filter` carries the two text fields:

```php
use Filament\Tables\Filters\Filter;

Filter::make('payment_search')
    ->schema([
        TextInput::make('transaction_id')
            ->label('Transaction ID'),
        TextInput::make('send_from_mobile_number')
            ->label('Send From'),
    ])
    ->query(fn (Builder $query, array $data): Builder => $query
        ->when(
            filled($data['transaction_id'] ?? null),
            fn (Builder $query, string $value): Builder => $query->where('transaction_id', 'like', "%{$value}%"),
        )
        ->when(
            filled($data['send_from_mobile_number'] ?? null),
            fn (Builder $query, string $value): Builder => $query->where('send_from_mobile_number', 'like', "%{$value}%"),
        ),
    )
```

### Semantics

| Input | Behaviour |
|---|---|
| Both fields blank | No restriction — every payment for the registration is listed. |
| Only transaction ID filled | Rows whose `transaction_id` contains the term (case-insensitive). |
| Only send-from filled | Rows whose `send_from_mobile_number` contains the term (case-insensitive). |
| Both filled | **AND** — a row must satisfy both conditions. |

- Matching is a **partial, case-insensitive substring** (`LIKE %term%`). The
  `payments.transaction_id` column uses the `utf8mb4_unicode_ci` collation, so
  MySQL's `LIKE` is already case-insensitive; no `LOWER()` is needed.
- Partial matching matters in practice: a user reads a transaction ID off a bKash
  SMS and mistypes a character, and admins often remember only the last few digits
  of the number the money was sent from.
- The panel keeps Filament's standard **Apply** and **Reset** actions.
- A `Filter` with a custom schema has no `isActive` key, so Filament always calls
  the `query()` closure; the `when()` guards make each half optional.

### Built-in table search

`transaction_id` is already `searchable()`; `send_from_mobile_number` becomes
`searchable()` as well. The table's own search box therefore also matches both
columns across all rows, as a secondary path.

## Scope

The search is **scoped to the registration being viewed** — the relation manager
queries the owner record's `payments` relation. A transaction that belongs to a
different registration will not appear here. A global cross-registration lookup is
explicitly out of scope (see below).

## Error handling and edge cases

| Case | Behaviour |
|---|---|
| Registration has no payments | Filament's standard empty state. The empty-state description names the registration code so the admin can see the search scope they are in. |
| `transaction_id` / `send_from_mobile_number` are NULL | A `LIKE` comparison against NULL never matches, so those rows are excluded as soon as the corresponding field is filled. A blank input is simply not applied. |
| Search term with `%` or `_` | Treated as SQL wildcards by `LIKE`. Acceptable for an internal admin tool; no escaping is added. |
| Panel submitted with no terms | The `when()` guards skip both `where` clauses and all payments for the registration are listed. |

## Performance

No migration is required.

- `payments.transaction_id` already carries an index (from the original
  `create_payments_table` migration).
- `payments.send_from_mobile_number` is **not** indexed. It does not need to be:
  every query is already scoped to one registration's payments by the relation
  manager's owner-record constraint, and the `payments` table holds 7 rows today.
- If the table ever grows to the point where this matters, a composite index on
  `(registration_id, transaction_id)` and `(registration_id, send_from_mobile_number)`
  is the migration to add.

## Tests

New file `tests/Feature/AdminRegistrationPaymentsTabTest.php`, following the
`Livewire::actingAs(...)->test(...)` pattern already used by
`tests/Feature/AdminRegistrationSmsTest.php`.

1. `test_registration_page_renders_registration_and_payments_tabs`
   - The rendered page contains both the "Registration" and "Payments" tab labels.
   - The payments table is no longer rendered inline beneath the form.
2. `test_payments_tab_lists_payments_for_the_registration`
   - Both of the registration's payments appear in the table.
3. `test_search_by_transaction_id_filters_rows`
   - Setting the filter's transaction field to a substring of one row's
     transaction ID narrows the table to that row.
4. `test_search_by_send_from_mobile_number_filters_rows`
   - Setting the filter's send-from field narrows the table to the matching row.
5. `test_both_search_fields_are_combined_with_and`
   - A transaction ID and a send-from number that belong to *different* rows yield
     zero rows; the pair that belongs to one row yields exactly that row.
6. `test_search_does_not_leak_payments_from_other_registrations`
   - A payment attached to a second registration never appears, filtered or not.
7. Regression — the 10 cases in `tests/Feature/AdminRegistrationSmsTest.php` still
   pass unchanged.

## Files added / modified

| File | Change |
|---|---|
| `app/Filament/Resources/Registrations/Pages/ViewRegistration.php` | Add `hasCombinedRelationManagerTabsWithContent()`, `getContentTabLabel()`, `getContentTabPosition()` |
| `app/Filament/Resources/Registrations/RelationManagers/PaymentsRelationManager.php` | Add the `payment_search` filter; switch the filter layout to `AboveContent`; make `send_from_mobile_number` searchable; add an empty-state description |
| `tests/Feature/AdminRegistrationPaymentsTabTest.php` | NEW |

No migrations. No model changes. No new dependencies.

## Out of scope

- A global payments list (`/admin/payments`) that searches across every reunion.
  The admin works inside a registration here; if a transaction is not found, they
  still have to locate the registration by other means.
- Sending an SMS from the payments table. The existing confirmation SMS is already
  triggered by the registration's `payment_status` transition and by the
  "Resend Confirmation SMS" header action on the registration page.
- Editing or deleting payments from the tab. The relation manager currently
  supports create and delete only; that is unchanged.
- Recording a payment that arrived outside the public flow (e.g. a cash handover).
- Full-text or fuzzy matching. Substring `LIKE` covers the realistic cases.
