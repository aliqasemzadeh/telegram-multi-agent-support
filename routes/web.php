<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::middleware(['permission:schedule.check-in'])->group(function () {
        Route::livewire('user', 'pages::user.index')->name('user.index');
    });

    Route::middleware(['permission:schedule.manage-own'])->group(function () {
        Route::livewire('user/week', 'pages::user.week')->name('user.week');
    });

    Route::middleware(['permission:schedule.view-coverage'])->group(function () {
        Route::livewire('admin/coverage/{week?}', 'pages::admin.coverage')->name('admin.coverage');
        Route::livewire('admin/agents/{user}/week/{week?}', 'pages::admin.agent-week')->name('admin.agents.week');
    });

    Route::middleware(['permission:schedule.lock-week'])->group(function () {
        Route::livewire('admin/weeks', 'pages::admin.weeks')->name('admin.weeks');
    });

    Route::middleware(['permission:agents.manage'])->group(function () {
        Route::livewire('admin/agents', 'pages::admin.agents')->name('admin.agents');
    });
});

require __DIR__.'/settings.php';
