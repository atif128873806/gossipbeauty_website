<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\AdminPreviewController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

/*
| Public website routes.
|
| Built: Home, Services & pricing, Our work & reviews, and the booking flow
| (the steps themselves are the Livewire component on /booking). About, Visit,
| Policies and service-detail pages are not built yet; do not add routes for
| them that would render empty pages.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/services', ServicesController::class)->name('services');

Route::get('/work', WorkController::class)->name('work');

Route::get('/booking', [BookingController::class, 'index'])->name('booking');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login.store');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [PanelController::class, 'index'])->name('index');
    Route::get('/customers', [PanelController::class, 'index'])->name('customers.index');
    Route::get('/services', [PanelController::class, 'index'])->name('services.index');
    Route::get('/team', [PanelController::class, 'index'])->name('team.index');
    Route::get('/availability', [PanelController::class, 'index'])->name('availability.index');
    Route::get('/accounts', [PanelController::class, 'index'])->name('accounts.index');
    Route::get('/settings', [PanelController::class, 'index'])->name('settings.index');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
    Route::put('/profile/password', [AdminAuthController::class, 'updatePassword'])->name('profile.password');
    Route::post('/appointments', [PanelController::class, 'manualBooking'])->name('appointments.store');
    Route::post('/appointments/import-existing', [PanelController::class, 'importExistingAppointment'])->name('appointments.import-existing');
    Route::post('/customers/search', [PanelController::class, 'searchCustomers'])->middleware('throttle:30,1')->name('customers.search');
    Route::put('/customers/{customer}/notes', [PanelController::class, 'updateCustomerNotes'])->name('customers.notes');
    Route::get('/customers/clear-search', [PanelController::class, 'clearCustomerSearch'])->name('customers.clear-search');
    Route::post('/services', [PanelController::class, 'createService'])->name('services.store');
    Route::post('/add-ons', [PanelController::class, 'createAddOn'])->name('add-ons.store');
    Route::put('/add-ons/{addOn}', [PanelController::class, 'saveAddOn'])->name('add-ons.update');
    Route::put('/services/{service}', [PanelController::class, 'saveService'])->name('services.update');
    Route::patch('/services/{service}/add-ons', [PanelController::class, 'saveServiceAddOns'])->name('services.add-ons');
    Route::post('/team', [PanelController::class, 'createStaff'])->name('staff.store');
    Route::put('/team/{staff}', [PanelController::class, 'saveStaff'])->name('staff.update');
    Route::post('/users', [PanelController::class, 'createUser'])->name('users.store');
    Route::patch('/users/{user}/access', [PanelController::class, 'updateUserAccess'])->name('users.access');
    Route::patch('/appointments/{appointment}/status', [PanelController::class, 'status'])->name('appointments.status');
    Route::patch('/appointments/{appointment}/reschedule', [PanelController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/notifications/{delivery}/retry', [PanelController::class, 'retryNotification'])->middleware('throttle:5,1')->name('appointments.notifications.retry');
    Route::patch('/settings/booking', [PanelController::class, 'toggleBooking'])->name('booking.update');
    Route::put('/settings/booking-rules', [PanelController::class, 'saveBookingRules'])->name('booking.rules');
    Route::patch('/services/{service}/booking', [PanelController::class, 'toggleService'])->name('services.booking');
    Route::patch('/team/{staff}/booking', [PanelController::class, 'toggleStaff'])->name('staff.booking');
    Route::put('/team/{staff}/hours', [PanelController::class, 'saveHours'])->name('staff.hours');
    Route::post('/availability-exceptions', [PanelController::class, 'createException'])->name('availability.exceptions.store');
    Route::delete('/availability-exceptions/{exception}', [PanelController::class, 'deleteException'])->name('availability.exceptions.delete');
});

/*
| Legacy studio panel preview.
|
| The panel layout built against an illustrative day: it reads one fixture, performs no
| action, writes nothing and is noindexed. It is gated on config('gossips.studio_panel
| .preview'), so switching it off makes the route a plain 404. The live panel
| already runs under /admin with authentication and per-action authorisation.
*/
Route::get('/admin/preview', AdminPreviewController::class)->name('admin.preview');
