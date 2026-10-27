<?php

namespace App\Http\Controllers;

use App\Models\Reunion;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Carbon;

/**
 * Renders the printable "Confirmed Registrations" sheet for a single reunion.
 *
 * The view is intentionally heavy on styling and light on data — we pull the
 * confirmed rows with their `student`, `guests`, and `payments` relations in
 * one query, then defer rendering to the Blade view, which is much faster than
 * building a DOCX archive in PHP and serves the admin a preview before they
 * print to PDF.
 */
class ConfirmedRegistrationsPrintController extends Controller
{
    public function __invoke(Reunion $reunion): Renderable
    {
        $registrations = $reunion->registrations()
            ->with(['student', 'guests', 'payments'])
            ->where('registration_status', 'confirmed')
            ->orderBy('id')
            ->get();

        return view('exports.confirmed-registrations', [
            'reunion' => $reunion,
            'registrations' => $registrations,
            'generatedAt' => Carbon::now(),
        ]);
    }
}
