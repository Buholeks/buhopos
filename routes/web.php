<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('/{any}', 'app')
    ->where('any', '^(?!api|sanctum|up).*$');


    