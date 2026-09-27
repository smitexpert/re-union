<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\ReunionRegistration;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentController extends Controller
{
    public function show(string $registration): Response|RedirectResponse
    {
        $reg = ReunionRegistration::query()
            ->with(['reunion', 'student', 'guests'])
            ->where('registration_code', $registration)
            ->first();

        if (! $reg) {
            abort(404);
        }

        $latestPayment = $reg->payments()->orderByDesc('id')->first();
        $rawStatus = $latestPayment?->status;
        $latestPaymentStatus = is_string($rawStatus) ? strtolower(trim($rawStatus)) : null;

        // Suppress the form whenever ANY non-failed payment attempt exists for
        // this registration, regardless of which attempt is "latest". This
        // protects against orphans and ordering surprises.
        $hasAnyAttemptedPayment = $reg->payments()
            ->whereIn('status', ['pending', 'completed'])
            ->exists();

        $showForm = ($latestPaymentStatus === null || $latestPaymentStatus === 'failed')
            && ! $hasAnyAttemptedPayment;

        $guests = $reg->guests;
        $adultCount = $guests->where('guest_type', 'Adult')->count();
        $childCount = $guests->where('guest_type', 'Child')->count();

        return Inertia::render('Payment', [
            'showForm' => $showForm,
            'latestPaymentStatus' => $latestPaymentStatus,
            'registration' => [
                'id' => $reg->id,
                'code' => $reg->registration_code,
                'registration_status' => $reg->registration_status,
                'payment_status' => $reg->payment_status,
                'total_fee' => (float) $reg->total_fee,
                'reunion_title' => $reg->reunion?->title,
                'event_date' => optional($reg->reunion?->event_date)->toDateString(),
                'student_name' => $reg->student?->full_name,
                'tshirt_size' => $reg->tshirt_size,
                'guest_count_adult' => $adultCount,
                'guest_count_child' => $childCount,
                'guests' => $guests
                    ->map(fn ($g) => [
                        'guest_type' => $g->guest_type,
                        'fee' => (float) $g->fee,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function store(StorePaymentRequest $request, string $registration): RedirectResponse
    {
        $reg = ReunionRegistration::query()
            ->where('registration_code', $registration)
            ->first();

        if (! $reg) {
            abort(404);
        }

        $alreadyCompleted = $reg->payments()->where('status', 'completed')->exists();
        if ($alreadyCompleted) {
            return redirect()
                ->route('payments.show', ['registration' => $reg->registration_code])
                ->with('status', 'A completed payment already exists for this registration.');
        }

        Payment::create([
            'registration_id' => $reg->id,
            'amount' => $request->validated('amount'),
            'payment_method' => $request->validated('payment_method'),
            'transaction_id' => $request->validated('transaction_id'),
            'send_from_mobile_number' => $request->validated('send_from_mobile_number'),
            'status' => 'pending',
            'paid_at' => null,
            'notes' => $request->validated('notes'),
        ]);

        return redirect()
            ->route('payments.show', ['registration' => $reg->registration_code])
            ->with('status', 'Payment submitted — pending review.');
    }
}
