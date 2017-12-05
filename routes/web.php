<?php

Route::get('/', 'Home@getHome')
     ->name('home');

Route::get('/home', 'Home@getHome')
     ->name('home.home');

Route::get('/index', 'Home@getHome')
     ->name('home.index');


// Register
Route::namespace('Auth')->prefix('register')->middleware('guest')->group(function () {

    Route::get('/', 'Register@getRegister')
         ->name('register');

    Route::post('/', 'Register@postRegister')
         ->name('register.post')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('success', 'Register@getRegisterSuccess')
         ->name('register.success');
});

// Login
Route::namespace('Auth')->group(function () {

    Route::get('login', 'Login@getLogin')
         ->name('login')
         ->middleware('guest');

    Route::post('login', 'Login@postLogin')
         ->name('login.post')
         ->middleware('throttle:100,1', 'guest', 'antispam');

    Route::post('logout', 'Login@postLogout')
         ->name('logout')
         ->middleware('auth');
});

// Password forgot/reset
Route::namespace('Auth\\Password\\Reset')->prefix('password')->middleware('guest')->group(function () {

    // Forgot
    Route::get('forgot', 'Forgot@getPasswordForgotForm')
         ->name('password.forgot');

    Route::post('forgot', 'Forgot@postPasswordForgotForm')
         ->name('password.forgot.post')
         ->middleware('throttle:100,1', 'antispam');

    // Reset
    Route::get('reset', 'Reset@getPasswordResetForm')
         ->name('password.reset');

    Route::post('reset', 'Reset@postPasswordResetForm')
         ->name('password.reset.post')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('reset/error', 'Reset@getPasswordResetError')
         ->name('password.reset.error');
});

// Email Verification
Route::namespace('Auth\\Email\\Verification')->prefix('email')->group(function () {

    Route::get('verify', 'Verification@getVerify')
         ->name('email.verify')
         ->middleware('throttle:100,1');

    Route::post('verify', 'Verification@postVerify')
         ->name('email.verify.post')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('verify/refresh', 'Verification@getVerificationRefreshForm')
         ->name('email.verify.refresh');

    Route::post('verify/refresh', 'Verification@postVerificationRefreshForm')
         ->name('email.verify.refresh.post')
         ->middleware('throttle:100,1', 'antispam');

    Route::get('verify/result', 'Verification@getVerifyResult')
         ->name('email.verify.result');
});



