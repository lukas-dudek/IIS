<?php

declare(strict_types=1);

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/prihlaseni', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/prihlaseni', [AuthController::class, 'login'])->name('login.store');
    Route::get('/registrace', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registrace', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/odhlaseni', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/udalosti', [EventController::class, 'index'])->name('events.index');

Route::get('/mista', [VenueController::class, 'index'])->name('venues.index');

Route::middleware('auth')->group(function (): void {
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profil/heslo', [ProfileController::class, 'password'])->name('profile.password');
    Route::get('/moje-navrhy', [ProfileController::class, 'proposals'])->name('proposals.index');

    Route::get('/mista/navrhnout', [VenueController::class, 'propose'])->name('venues.propose');
    Route::post('/mista/navrhnout', [VenueController::class, 'store'])->name('venues.store');
    Route::get('/mista/{venue}/upravit', [VenueController::class, 'editProposal'])->name('venues.edit-proposal');
    Route::post('/mista/{venue}/upravit', [VenueController::class, 'updateProposal'])->name('venues.update-proposal');

    Route::get('/kategorie/navrhnout', [CategoryController::class, 'propose'])->name('categories.propose');
    Route::post('/kategorie/navrhnout', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/kategorie/{category}/upravit', [CategoryController::class, 'editProposal'])->name('categories.edit-proposal');
    Route::post('/kategorie/{category}/upravit', [CategoryController::class, 'updateProposal'])->name('categories.update-proposal');

    Route::get('/moje-udalosti', [EventController::class, 'mine'])->name('events.mine');
    Route::get('/udalosti/nova', [EventController::class, 'create'])->name('events.create');
    Route::post('/udalosti', [EventController::class, 'store'])->name('events.store');
    Route::get('/udalosti/{event:id}/upravit', [EventController::class, 'edit'])->name('events.edit');
    Route::post('/udalosti/{event:id}', [EventController::class, 'update'])->name('events.update');
    Route::post('/udalosti/{event:id}/odeslat', [EventController::class, 'submit'])->name('events.submit');
    Route::post('/udalosti/{event:id}/zrusit', [EventController::class, 'cancel'])->name('events.cancel');

    Route::post('/udalosti/{event:slug}/registrace', [RegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/moje-registrace', [RegistrationController::class, 'index'])->name('registrations.index');
    Route::post('/registrace/{registration}/zrusit', [RegistrationController::class, 'cancel'])
        ->name('registrations.cancel');
    Route::post('/registrace/{registration}/uhrazeno', [RegistrationController::class, 'claimPayment'])
        ->name('registrations.claim-payment');
    Route::get('/udalosti/{event:id}/ucastnici', [RegistrationController::class, 'participants'])
        ->name('registrations.participants');
    Route::post('/registrace/{registration}/potvrdit', [RegistrationController::class, 'confirmPayment'])
        ->name('registrations.confirm-payment');
    Route::post('/registrace/{registration}/odmitnout', [RegistrationController::class, 'rejectPayment'])
        ->name('registrations.reject-payment');

    Route::post('/udalosti/{event:slug}/komentar', [CommentController::class, 'store'])->name('comments.store');
    Route::post('/komentar/{comment}/upravit', [CommentController::class, 'update'])->name('comments.update');
    Route::post('/komentar/{comment}/smazat', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/udalosti/{event:slug}/hodnoceni', [RatingController::class, 'store'])->name('ratings.store');

    Route::get('/muj-kalendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/muj-kalendar.ics', [CalendarController::class, 'export'])->name('calendar.export');
});

Route::get('/udalosti/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::get('/mista/{venue:id}', [VenueController::class, 'show'])->name('venues.show');

Route::middleware(['auth', 'moderator'])->prefix('moderace')->name('moderation.')->group(function (): void {
    Route::get('/', [ModerationController::class, 'index'])->name('index');

    Route::get('/udalosti', [ModerationController::class, 'events'])->name('events');
    Route::get('/udalosti/{event}', [ModerationController::class, 'event'])->name('event');
    Route::post('/udalosti/{event}/schvalit', [ModerationController::class, 'approveEvent'])->name('events.approve');
    Route::post('/udalosti/{event}/zamitnout', [ModerationController::class, 'rejectEvent'])->name('events.reject');

    Route::get('/mista', [ModerationController::class, 'venues'])->name('venues');
    Route::get('/mista/novy', [ModerationController::class, 'createVenue'])->name('venues.create');
    Route::post('/mista/novy', [ModerationController::class, 'storeVenue'])->name('venues.store');
    Route::get('/mista/{venue}', [ModerationController::class, 'venue'])->name('venue');
    Route::get('/mista/{venue}/upravit', [ModerationController::class, 'editVenue'])->name('venues.edit');
    Route::post('/mista/{venue}/upravit', [ModerationController::class, 'updateVenue'])->name('venues.update');
    Route::post('/mista/{venue}/schvalit', [ModerationController::class, 'approveVenue'])->name('venues.approve');
    Route::post('/mista/{venue}/zamitnout', [ModerationController::class, 'rejectVenue'])->name('venues.reject');
    Route::post('/mista/{venue}/smazat', [ModerationController::class, 'deleteVenue'])->name('venues.delete');

    Route::get('/kategorie', [ModerationController::class, 'categories'])->name('categories');
    Route::get('/kategorie/nova', [ModerationController::class, 'createCategory'])->name('categories.create');
    Route::post('/kategorie/nova', [ModerationController::class, 'storeCategory'])->name('categories.store');
    Route::get('/kategorie/{category}', [ModerationController::class, 'category'])->name('category');
    Route::get('/kategorie/{category}/upravit', [ModerationController::class, 'editCategory'])->name('categories.edit');
    Route::post('/kategorie/{category}/upravit', [ModerationController::class, 'updateCategory'])->name('categories.update');
    Route::post('/kategorie/{category}/schvalit', [ModerationController::class, 'approveCategory'])->name('categories.approve');
    Route::post('/kategorie/{category}/zamitnout', [ModerationController::class, 'rejectCategory'])->name('categories.reject');
    Route::post('/kategorie/{category}/smazat', [ModerationController::class, 'deleteCategory'])->name('categories.delete');

    Route::get('/komentare', [ModerationController::class, 'comments'])->name('comments');
    Route::post('/komentare/{comment}/skryt', [ModerationController::class, 'hideComment'])->name('comments.hide');
    Route::post('/komentare/{comment}/zobrazit', [ModerationController::class, 'showComment'])->name('comments.show');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/uzivatele', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/uzivatele/novy', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/uzivatele/novy', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/uzivatele/{user}/upravit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::post('/uzivatele/{user}/upravit', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('/uzivatele/{user}/heslo', [AdminUserController::class, 'resetPassword'])->name('users.password');
    Route::post('/uzivatele/{user}/deaktivovat', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/uzivatele/{user}/aktivovat', [AdminUserController::class, 'activate'])->name('users.activate');
    Route::post('/uzivatele/{user}/smazat', [AdminUserController::class, 'destroy'])->name('users.destroy');
});
