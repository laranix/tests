<?php

Route::get('/', 'Home@show')
     ->name('home');

Route::get('/home', 'Home@show')
     ->name('home.home');

Route::get('/index', 'Home@show')
     ->name('home.index');


// Register
Route::name('register.')->namespace('Auth')->prefix('register')->middleware('guest')->group(function () {
    Route::get('/', 'Register@create')
         ->name('create');

    Route::post('/', 'Register@store')
         ->name('store')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('success', 'Register@success')
         ->name('success');
});

// Login
Route::namespace('Auth')->group(function () {
    Route::get('login', 'Login@show')
         ->name('login.show')
         ->middleware('guest');

    Route::post('login', 'Login@doLogin')
         ->name('login')
         ->middleware('throttle:100,1', 'guest', 'antispam');

    Route::post('logout', 'Login@doLogout')
         ->name('logout')
         ->middleware('auth');
});

// Password forgot/reset
Route::name('password.')->namespace('Auth\\Password\\Reset')->prefix('password')->middleware('guest')->group(function () {
     // Forgot
     Route::get('/forgot', 'Forgot@create')
          ->name('forgot.create');

     Route::post('/forgot', 'Forgot@store')
          ->name('forgot.store')
          ->middleware('throttle:100,1', 'antispam');

     // Reset
     Route::get('/reset', 'Reset@show')
          ->name('reset.show');

     Route::post('/reset', 'Reset@update')
          ->name('reset')
          ->middleware('throttle:100,1', 'antispam');

     Route::get('/reset/error', 'Reset@error')
          ->name('reset.error');
});

// Email Verification
Route::name('email.verify.')->namespace('Auth\\Email\\Verification')->prefix('email')->group(function () {
    Route::get('verify', 'Verification@show')
         ->name('show')
         ->middleware('throttle:100,1');

    Route::post('verify', 'Verification@update')
         ->name('update')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('verify/refresh', 'Verification@create')
         ->name('create');

    Route::post('verify/refresh', 'Verification@store')
         ->name('store')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('verify/result', 'Verification@result')
         ->name('result');
});



