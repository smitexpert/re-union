# Admin Registration Payments Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the admin registration page a real "Payments" tab, and a search panel on it that filters that registration's payments by transaction ID and by the mobile number the money was sent from.

**Architecture:** `ViewRegistration` opts into Filament's combined content + relation-manager tab mode, turning the payments block that currently renders bare under the form into a second tab labelled "Payments", sitting next to a "Registration" tab that holds the existing form. `PaymentsRelationManager` gains a custom-schema table filter (`payment_search`) whose panel is pinned above the table with `FiltersLayout::AboveContent`; filters are **not** deferred, so rows narrow as the admin types.

**Tech Stack:** Laravel 13, PHP 8.5, Filament v4 (panels / tables / Livewire v3), PHPUnit 12, SQLite in-memory test database.

**Spec:** `docs/superpowers/specs/2026-10-29-admin-registration-payments-tab-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `app/Filament/Resources/Registrations/Pages/ViewRegistration.php` | The registration page. Owns the tab bar — which tab holds the form, which holds the payments. |
| `app/Filament/Resources/Registrations/RelationManagers/PaymentsRelationManager.php` | The Payments tab's contents. Owns the payments table and the transaction / send-from search filter. |
| `tests/Feature/AdminRegistrationPaymentsTabTest.php` | Covers both halves: the tab bar on the page, and the search behaviour in the relation manager. |

Nothing else changes — no migrations, no model changes, no new dependencies.

## Filament mechanics this plan relies on (verified against vendor source)

These were checked in `vendor/filament/` before writing the plan. Do not re-derive them.

| Fact | Source |
|---|---|
| A page renders relation managers as **tabs** only when it declares more than one; a single manager renders bare. | `filament/src/Resources/Pages/Concerns/HasRelationManagers.php:134` |
| `hasCombinedRelationManagerTabsWithContent() === true` puts the page content **and** the relation managers into one tab bar. | same file, `getRelationManagersContentComponent()` |
| The content tab's array key is `''`; `activeRelationManager` is `null` until a manager tab is clicked, so `strval(null) === strval('')` and the **content tab is active by default**. | `schemas/src/Components/Tabs/Tab.php:190` (`if (strval(...) !== strval($key)) return '';`) |
| `EditRecord::getContentTabComponent()` already places the form inside the content tab, so `data.*` state, the save button and `afterSave()` are unaffected. | `filament/src/Resources/Pages/EditRecord.php:382` |
| Only the **active** tab's content is rendered, so an inactive Payments tab contributes no table markup. | `schemas/src/Components/Tabs/Tab.php:190` |
| Filter state lives in `tableFilters` (applied, read by the query) and `tableDeferredFilters` (form-bound). `applyTableFilters()` copies deferred → applied. | `tables/src/Concerns/HasFilters.php:162` |
| With `deferFilters(false)` the filters form binds to `tableFilters` and is `live()`; the Apply button is hidden (`->visible($this->hasDeferredFilters())`). | `tables/src/Concerns/HasFilters.php:30`, `tables/src/Table/Concerns/HasFilters.php:288` |
| A filter's Livewire state path is `tableFilters.<filter name>.<field name>`; `parseTableFilterName()` returns the literal name for a non-class name. | `tables/src/Concerns/HasFilters.php:196`, `tables/src/Table/Concerns/HasFilters.php` (`getFiltersFormSchema()`) |
| `FiltersLayout::AboveContent` renders the filters form as a panel above the table. | `tables/src/Enums/FiltersLayout.php`, `tables/resources/views/index.blade.php:126` |
| `assertTableFilterExists($name, $checkFilterUsing)` and `assertCanSeeTableRecords()` exist as Livewire test macros. | `tables/src/Testing/TestsFilters.php:86`, `tables/src/Testing/TestsRecords.php:20` |

---

## Task 1: The registration page renders a "Payments" tab

**Files:**
- Create: `tests/Feature/AdminRegistrationPaymentsTabTest.php`
- Modify: `app/Filament/Resources/Registrations/Pages/ViewRegistration.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminRegistrationPaymentsTabTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Models\Payment;
use App\Models\Reunion;
use App\Models\ReunionRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRegistrationPaymentsTabTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function registration(): ReunionRegistration
    {
        $reunion = Reunion::factory()->create(['title' => 'SWAATC Reunion 2026']);
        $student = Student::factory()->create(['phone' => '01712345678']);

        return ReunionRegistration::factory()->create([
            'reunion_id' => $reunion->id,
            'student_id' => $student->id,
            'registration_status' => 'confirmed',
            'payment_status' => 'paid',
            'total_fee' => '1000.00',
        ]);
    }

    /**
     * Pull the tab labels out of the rendered tab bar.
     *
     * @return list<string>
     */
    private function tabLabels(string $html): array
    {
        preg_match_all('/fi-tabs-item-label[^>]*>\s*([^<]+)/', $html, $matches);

        return array_map('trim', $matches[1]);
    }

    public function test_registration_page_renders_registration_and_payments_tabs(): void
    {
        $registration = $this->registration();

        Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        $html = Livewire::actingAs($this->admin())
            ->test(ViewRegistration::class, ['record' => $registration->id])
            ->html();

        $labels = $this->tabLabels($html);

        $this->assertContains('Registration', $labels);
        $this->assertContains('Payments', $labels);

        // The payments table is no longer rendered inline beneath the form.
        $this->assertStringNotContainsString('TXN-AAA111', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/AdminRegistrationPaymentsTabTest.php`

Expected: FAIL — `assertContains('Registration', $labels)` fails because the page renders no tab bar at all; `$labels` is empty.

- [ ] **Step 3: Implement the combined tab bar**

In `app/Filament/Resources/Registrations/Pages/ViewRegistration.php`, add the import:

```php
use Filament\Resources\Pages\Enums\ContentTabPosition;
```

Place it in alphabetical position among the existing `use` statements — after `use Filament\Notifications\Notification;` and before `use Filament\Resources\Pages\EditRecord;`.

Then add these three methods immediately after `getRedirectUrl()`:

```php
    /**
     * Render the page as a tab bar: "Registration" holds the form, "Payments"
     * holds the PaymentsRelationManager.
     *
     * Filament only tabs relation managers when a page declares more than one,
     * so this page opts into the combined content + relation-manager tabs
     * explicitly. Without it the payments table renders as a bare block under
     * the form.
     */
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

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/AdminRegistrationPaymentsTabTest.php`

Expected: PASS.

If `assertContains('Registration', $labels)` fails while `Payments` passes, the content tab's label is not being picked up from `getContentTabLabel()` — check that the method is `public` and returns a non-null string.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Registrations/Pages/ViewRegistration.php tests/Feature/AdminRegistrationPaymentsTabTest.php
git commit -m "feat(filament): render admin registration page as Registration + Payments tabs

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

## Task 2: The transaction / send-from search panel

**Files:**
- Modify: `tests/Feature/AdminRegistrationPaymentsTabTest.php`
- Modify: `app/Filament/Resources/Registrations/RelationManagers/PaymentsRelationManager.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/AdminRegistrationPaymentsTabTest.php`:

```php
    public function test_payments_tab_lists_payments_for_the_registration(): void
    {
        $registration = $this->registration();

        $first = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        $second = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-BBB222',
            'send_from_mobile_number' => '01722222222',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            ->assertCanSeeTableRecords([$first, $second]);
    }

    public function test_search_by_transaction_id_filters_rows(): void
    {
        $registration = $this->registration();

        $match = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        $other = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-BBB222',
            'send_from_mobile_number' => '01722222222',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            ->set('tableFilters.payment_search.transaction_id', 'AAA111')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_by_send_from_mobile_number_filters_rows(): void
    {
        $registration = $this->registration();

        $match = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        $other = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-BBB222',
            'send_from_mobile_number' => '01722222222',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            ->set('tableFilters.payment_search.send_from_mobile_number', '22222222')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$match]);
    }

    public function test_both_search_fields_are_combined_with_and(): void
    {
        $registration = $this->registration();

        $match = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        $other = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-BBB222',
            'send_from_mobile_number' => '01722222222',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            // Both terms point at the same row.
            ->set('tableFilters.payment_search.transaction_id', 'AAA111')
            ->set('tableFilters.payment_search.send_from_mobile_number', '01711111111')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_search_terms_from_different_rows_match_nothing(): void
    {
        $registration = $this->registration();

        Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-BBB222',
            'send_from_mobile_number' => '01722222222',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            ->set('tableFilters.payment_search.transaction_id', 'AAA111')
            ->set('tableFilters.payment_search.send_from_mobile_number', '01722222222')
            ->assertCanNotSeeTableRecords(Payment::all());
    }

    public function test_search_does_not_leak_payments_from_other_registrations(): void
    {
        $registration = $this->registration();

        $otherRegistration = $this->registration();

        // The search term matches the foreign payment exactly, but the table is
        // scoped to the registration being viewed.
        $foreign = Payment::factory()->create([
            'registration_id' => $otherRegistration->id,
            'transaction_id' => 'TXN-AAA111',
            'send_from_mobile_number' => '01711111111',
        ]);

        $local = Payment::factory()->create([
            'registration_id' => $registration->id,
            'transaction_id' => 'TXN-LOCAL1',
            'send_from_mobile_number' => '01799999999',
        ]);

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            ->set('tableFilters.payment_search.transaction_id', 'AAA111')
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertCanNotSeeTableRecords([$local]);
    }

    public function test_search_panel_exposes_both_fields(): void
    {
        $registration = $this->registration();

        Livewire::actingAs($this->admin())
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $registration,
                'pageClass' => ViewRegistration::class,
            ])
            ->assertTableFilterExists('payment_search', function (BaseFilter $filter): bool {
                $names = array_map(
                    fn (Component $component): string => $component->getName(),
                    array_filter(
                        $filter->getSchemaComponents(),
                        fn ($component): bool => $component instanceof Component,
                    ),
                );

                return $names === ['transaction_id', 'send_from_mobile_number'];
            });
    }
```

Add the three new imports to the top of the test file:

```php
use App\Filament\Resources\Registrations\RelationManagers\PaymentsRelationManager;
use Filament\Schemas\Components\Component;
use Filament\Tables\Filters\BaseFilter;
```

(`Livewire\Livewire`, `Payment`, `Reunion`, `ReunionRegistration`, `Student`, `User`, `RefreshDatabase` and `TestCase` are already imported by Task 1.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/AdminRegistrationPaymentsTabTest.php`

Expected: the Task 1 test still PASSES; the new tests FAIL with a Livewire error such as "Property [tableFilters.payment_search.transaction_id] not found" or an assertion failure showing every row is still visible (the filter does not exist yet).

- [ ] **Step 3: Implement the search filter**

In `app/Filament/Resources/Registrations/RelationManagers/PaymentsRelationManager.php`, add these imports:

```php
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
```

(`Filament\Forms\Components\TextInput` is already imported.)

Then replace the `table()` method with:

```php
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_id')
            // Filter as the admin types — the panel is the primary affordance
            // of this tab, so an explicit Apply step would only slow it down.
            ->deferFilters(false)
            ->columns([
                TextColumn::make('amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'paid', 'success' => 'success',
                        'pending' => 'warning',
                        'failed', 'cancelled' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('send_from_mobile_number')
                    ->label('Send From')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('payment_search')
                    ->label('Search')
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
                    ),
                SelectFilter::make('payment_method')
                    ->options([
                        'bkash' => 'bKash',
                        'nagad' => 'Nagad',
                        'bank_transfer' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'card' => 'Card',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ], FiltersLayout::AboveContent)
            ->emptyStateDescription(fn (): string => 'No payments found for registration '.$this->getOwnerRecord()->registration_code.'.')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/AdminRegistrationPaymentsTabTest.php`

Expected: all tests PASS.

If `test_search_by_transaction_id_filters_rows` fails while the rows are all still visible, the filter state path is wrong — confirm it is `tableFilters.payment_search.transaction_id` (the filter name is `payment_search`, the field names are the two column names).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Registrations/RelationManagers/PaymentsRelationManager.php tests/Feature/AdminRegistrationPaymentsTabTest.php
git commit -m "feat(filament): search payments by transaction id and send-from number

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

---

## Task 3: Regression sweep and formatting

**Files:**
- No file changes expected — this task verifies and fixes formatting.

- [ ] **Step 1: Run the whole test suite**

Run: `php artisan test --compact`

Expected: every test passes, including the 10 cases in `tests/Feature/AdminRegistrationSmsTest.php` (the registration page's save + SMS behaviour must be unaffected by the tab restructure) and `tests/Feature/DashboardStatsWidgetTest.php`.

If `AdminRegistrationSmsTest` fails, the most likely cause is that the form is no longer mounted the way `Livewire::test(ViewRegistration::class, ...)` expects. Check that `hasCombinedRelationManagerTabsWithContent()` is `public` and that `getContentTabComponent()` was not overridden.

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

Expected: no changes, or only whitespace fixes that Pint reports as "fixed". Re-run `php artisan test --compact` if Pint changed anything.

- [ ] **Step 3: Sanity-check the rendered page**

Render the page once and read the output to confirm the panel and the tab bar look right:

```bash
php artisan test --compact --filter=test_registration_page_renders_registration_and_payments_tabs
```

Then, for a visual check of the real app, open `http://reunion.test/admin/registrations/{id}` in a browser as an admin user and confirm:

- the page shows a tab bar with **Registration** and **Payments**;
- the page opens on **Registration**, with the existing form intact;
- clicking **Payments** shows the search panel (Transaction ID + Send From + Method + Status) above the payments table;
- typing a partial transaction ID narrows the rows without an Apply click;
- **Reset** restores all rows.

- [ ] **Step 4: Commit any remaining changes**

```bash
git add -A
git commit -m "style: pint formatting for admin payments tab

Co-Authored-By: Claude Code <noreply@anthropic.com>"
```

Skip this step if Step 2 produced no changes — do not create an empty commit.
