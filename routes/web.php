<?php

use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/stores');

Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');

if (app()->environment('local')) {
    Route::livewire('/styleguide', 'styleguide')->name('styleguide');
}
