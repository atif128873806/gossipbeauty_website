<?php

namespace App\Http\Controllers;

use App\Models\BookingConfiguration;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Entry point for the booking journey.
     *
     * The journey itself is the `BookingJourney` Livewire component, which owns the steps
     * summarized in AGENTS.md. This controller only
     * resolves the optional preselection: choosing a service anywhere on the site carries
     * that choice into booking instead of asking the customer to pick again (03, 05).
     *
     * The slug is resolved against the active catalog and anything unrecognised is discarded
     * rather than reflected back into the page. The booking form is only rendered while the
     * studio has opened online booking; the write path still performs its own final checks.
     */
    public function index(Request $request): View
    {
        $slug = $request->query('service');

        $serviceSlug = is_string($slug)
            ? Service::query()->where('slug', $slug)->where('is_active', true)->where('is_online_bookable', true)->value('slug')
            : null;

        return view('booking.index', [
            'serviceSlug' => $serviceSlug,
            'onlineBookingEnabled' => BookingConfiguration::current()->online_booking_enabled,
        ]);
    }
}
