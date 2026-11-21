<?php

use App\Http\Controllers\LoyaltyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PosConnectionController;
use App\Http\Controllers\MemberPortalController;

Route::prefix('portal/{business}')->whereNumber('business')->name('portal.')->group(function () {
    Route::get('/', [MemberPortalController::class, 'login'])->name('login');
    Route::post('/code', [MemberPortalController::class, 'sendCode'])->name('code');
    Route::get('/verify', [MemberPortalController::class, 'verify'])->name('verify');
    Route::post('/verify', [MemberPortalController::class, 'confirm'])->name('confirm');
    Route::get('/home', [MemberPortalController::class, 'home'])->name('home');
    Route::post('/logout', [MemberPortalController::class, 'logout'])->name('logout');
});

Route::get('/login', [LoyaltyController::class, 'login'])->name('login');
Route::post('/login', [LoyaltyController::class, 'authenticate'])->middleware('throttle:5,1');
Route::post('/logout', [LoyaltyController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/pos-connections', [PosConnectionController::class, 'index'])->name('pos-connections');
    Route::post('/pos-connections', [PosConnectionController::class, 'store'])->name('pos-connections.store');
    Route::post('/pos-connections/{id}/revoke', [PosConnectionController::class, 'revoke'])->name('pos-connections.revoke');
    Route::get('/', [LoyaltyController::class, 'dashboard'])->name('dashboard');
    Route::get('/members', [LoyaltyController::class, 'members'])->name('members');
    Route::post('/members', [LoyaltyController::class, 'storeMember'])->name('members.store');
    Route::post('/members/{id}/adjust', [LoyaltyController::class, 'adjustMember'])->name('members.adjust');
    Route::get('/members/{id}', [LoyaltyController::class, 'member'])->name('members.show');
    Route::get('/checkout', [LoyaltyController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [LoyaltyController::class, 'storeCheckout'])->name('checkout.store');
    Route::get('/transactions', [LoyaltyController::class, 'transactions'])->name('transactions');
    Route::get('/transactions/{id}', [LoyaltyController::class, 'transaction'])->name('transactions.show');
    Route::post('/transactions/{id}/refund', [LoyaltyController::class, 'refund'])->name('transactions.refund');
    Route::get('/rules', [LoyaltyController::class, 'rules'])->name('rules');
    Route::post('/rules', [LoyaltyController::class, 'storeRule'])->name('rules.store');
    Route::get('/team', [LoyaltyController::class, 'team'])->name('team');
    Route::post('/team', [LoyaltyController::class, 'storeUser'])->name('team.store');
    Route::post('/outlets', [LoyaltyController::class, 'storeOutlet'])->name('outlets.store');
    Route::get('/audit', [LoyaltyController::class, 'audit'])->name('audit');
});
