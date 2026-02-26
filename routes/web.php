<?php

use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/stores');

Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
