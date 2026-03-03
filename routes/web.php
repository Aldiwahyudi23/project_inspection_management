<?php

use App\Http\Controllers\testcontroller;
use Illuminate\Support\Facades\Route;
use PHPUnit\Metadata\Test;

Route::get('/', function () {
    return view('welcome');
});
// routes/api.php
Route::get('/test/{id}', [testcontroller::class, 'show']);
Route::get('/test', [testcontroller::class, 'index']);

