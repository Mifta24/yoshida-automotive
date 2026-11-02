<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HandoverController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\KnowledgeItemController;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\ServiceTypeController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\WorkshopSettingsController;
use App\Http\Controllers\Admin\WorkshopSwitchController;
use App\Http\Controllers\AdvisorChatController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerBookingController;
use App\Http\Controllers\Member\AccountController as MemberAccountController;
use App\Http\Controllers\Member\BookingController as MemberBookingController;
use App\Http\Controllers\Member\LoginController as MemberLoginController;
use App\Http\Controllers\NarrationSpeechController;
use App\Http\Controllers\Platform\WorkshopController as PlatformWorkshopController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\WorkshopPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorkshopPageController::class, 'index'])->name('home');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('switch-workshop', WorkshopSwitchController::class)->name('switch-workshop');

        Route::middleware('workshop.can:content')->group(function () {
            Route::resource('service-types', ServiceTypeController::class)->except('show');
            Route::resource('knowledge-items', KnowledgeItemController::class)->except('show');
        });

        Route::middleware('workshop.can:inventory')->group(function () {
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::put('inventory', [InventoryController::class, 'update'])->name('inventory.update');
        });

        Route::middleware('workshop.can:bookings.view')->group(function () {
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        });
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->middleware('workshop.can:bookings.manage')->name('bookings.status');

        Route::middleware('workshop.can:handovers')->group(function () {
            Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
            Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
            Route::post('handovers/{handover}/reply', [HandoverController::class, 'reply'])->name('handovers.reply');
            Route::post('handovers/{handover}/resolve', [HandoverController::class, 'resolve'])->name('handovers.resolve');
        });

        Route::middleware('workshop.can:members')->group(function () {
            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::get('members/{member}', [MemberController::class, 'show'])->whereNumber('member')->name('members.show');
            Route::post('members/{member}/points', [MemberController::class, 'adjustPoints'])->whereNumber('member')->name('members.points');
        });

        Route::middleware('workshop.can:settings')->group(function () {
            Route::get('loyalty', [LoyaltyController::class, 'edit'])->name('loyalty.edit');
            Route::put('loyalty', [LoyaltyController::class, 'update'])->name('loyalty.update');
        });

        Route::post('notes/{type}/{id}', [NoteController::class, 'store'])->whereIn('type', ['booking', 'handover', 'member'])->whereNumber('id')->name('notes.store');

        Route::middleware('workshop.can:settings')->group(function () {
            Route::get('settings', [WorkshopSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [WorkshopSettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('workshop.can:team')->group(function () {
            Route::get('team', [TeamController::class, 'index'])->name('team.index');
            Route::post('team', [TeamController::class, 'store'])->name('team.store');
            Route::put('team/{member}', [TeamController::class, 'update'])->name('team.update');
            Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
        });

        Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');

        Route::get('analytics', [AnalyticsController::class, 'index'])->middleware('workshop.can:analytics')->name('analytics.index');
        Route::get('activity', [ActivityController::class, 'index'])->middleware('workshop.can:activity')->name('activity.index');
    });
});

Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform.admin'])->group(function () {
    Route::get('workshops', [PlatformWorkshopController::class, 'index'])->name('workshops.index');
    Route::post('workshops', [PlatformWorkshopController::class, 'store'])->name('workshops.store');
    Route::patch('workshops/{workshop}/ai-model', [PlatformWorkshopController::class, 'updateAiModel'])->name('workshops.ai-model');
    Route::patch('workshops/{workshop}/status', [PlatformWorkshopController::class, 'updateStatus'])->name('workshops.status');
    Route::patch('workshops/{workshop}/features', [PlatformWorkshopController::class, 'updateFeature'])->name('workshops.features');
});

Route::post('narration/speech', NarrationSpeechController::class)->middleware('throttle:narration-speech')->name('narration.speech');

Route::prefix('{workshopSlug}')->where(['workshopSlug' => '(?!(?:up|admin|platform|narration)$)[^/]+'])->group(function () {
    Route::get('/', [WorkshopPageController::class, 'show'])->name('workshop.show');
    Route::get('services', [WorkshopPageController::class, 'services'])->name('workshop.services');
    Route::get('services/{serviceSlug}', [WorkshopPageController::class, 'service'])->name('workshop.service');
    Route::get('facilities', [WorkshopPageController::class, 'facilities'])->name('workshop.facilities');
    Route::get('facilities/{facilityId}', [WorkshopPageController::class, 'facility'])->whereNumber('facilityId')->name('workshop.facility');
    Route::get('info', [WorkshopPageController::class, 'info'])->name('workshop.info');
    Route::get('staff', [WorkshopPageController::class, 'staff'])->name('workshop.staff');
    Route::get('reservation', [WorkshopPageController::class, 'reservationScene'])->name('workshop.reservation');
    Route::get('divisions/{division}', [WorkshopPageController::class, 'division'])->name('workshop.division');
    Route::get('company', [WorkshopPageController::class, 'company'])->name('workshop.company');

    Route::get('booking', [CustomerBookingController::class, 'page'])->name('workshop.booking');
    Route::prefix('booking')->name('booking.')->middleware('throttle:booking-manage')->group(function () {
        Route::post('lookup', [CustomerBookingController::class, 'lookup'])->name('lookup');
        Route::post('reschedule', [CustomerBookingController::class, 'reschedule'])->name('reschedule');
        Route::post('cancel', [CustomerBookingController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('member')->name('member.')->middleware('member.enabled')->group(function () {
        Route::get('login', [MemberLoginController::class, 'create'])->name('login');
        Route::post('login', [MemberLoginController::class, 'send'])->middleware('throttle:member-code')->name('login.send');
        Route::get('verify', [MemberLoginController::class, 'verifyForm'])->name('verify');
        Route::post('verify', [MemberLoginController::class, 'verify'])->middleware('throttle:member-verify')->name('verify.check');

        Route::middleware('member.auth')->group(function () {
            Route::get('/', [MemberAccountController::class, 'show'])->name('account');
            Route::put('profile', [MemberAccountController::class, 'updateProfile'])->name('profile');
            Route::post('logout', [MemberLoginController::class, 'destroy'])->name('logout');
            Route::post('bookings/{reference}/reschedule', [MemberBookingController::class, 'reschedule'])->name('bookings.reschedule');
            Route::post('bookings/{reference}/cancel', [MemberBookingController::class, 'cancel'])->name('bookings.cancel');
        });
    });

    Route::prefix('reservation')->name('reservation.')->middleware('throttle:20,1')->group(function () {
        Route::post('quote', [ReservationController::class, 'quote'])->name('quote');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
    });

    Route::prefix('advisor')->name('advisor.')->group(function () {
        Route::post('start', [AdvisorChatController::class, 'start'])->middleware('throttle:advisor-start')->name('start');
        Route::post('message', [AdvisorChatController::class, 'message'])->middleware('throttle:advisor-message')->name('message');
        Route::post('feedback', [AdvisorChatController::class, 'feedback'])->middleware('throttle:advisor-feedback')->name('feedback');
        Route::get('history', [AdvisorChatController::class, 'history'])->middleware('throttle:advisor-history')->name('history');
    });
});
