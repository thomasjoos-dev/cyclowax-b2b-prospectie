<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        return view('stores.index');
    }
}
