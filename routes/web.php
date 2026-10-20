<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// HU05: inicio de sesión. El nombre "login" es adonde Laravel manda a los invitados.
Route::view('/login', 'auth.login')->middleware('guest')->name('login');

// Cierre de sesión: invalida la sesión y el token CSRF para que no se puedan reutilizar
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

// Panel de inicio de cada rol (solo usuarios con ese rol)
Route::middleware(['auth', 'role:administrador'])->get('/admin/inicio', fn () => view('panel.inicio'));
Route::middleware(['auth', 'role:profesor'])->get('/profesor/inicio', fn () => view('panel.inicio'));
Route::middleware(['auth', 'role:alumno'])->get('/alumno/inicio', fn () => view('panel.inicio'));
Route::middleware(['auth', 'role:auditor'])->get('/auditor/inicio', fn () => view('panel.inicio'));
