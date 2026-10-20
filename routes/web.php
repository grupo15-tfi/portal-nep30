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

// Paneles por rol: cada grupo exige sesión iniciada y el rol correspondiente.
// Las rutas nuevas de cada rol van dentro de su grupo (URL /<prefijo>/..., nombre <rol>.<ruta>).

Route::middleware(['auth', 'role:administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/inicio', fn () => view('panel.inicio'))->name('inicio');
    // HU07 (#9): rutas de sedes, cursos y planes van acá
});

Route::middleware(['auth', 'role:profesor'])->prefix('profesor')->name('profesor.')->group(function () {
    Route::get('/inicio', fn () => view('panel.inicio'))->name('inicio');
    // Historias del rol Profesor: sus rutas van acá
});

Route::middleware(['auth', 'role:alumno'])->prefix('alumno')->name('alumno.')->group(function () {
    Route::get('/inicio', fn () => view('panel.inicio'))->name('inicio');
    // Historias del rol Alumno: sus rutas van acá
});

Route::middleware(['auth', 'role:auditor'])->prefix('auditor')->name('auditor.')->group(function () {
    Route::get('/inicio', fn () => view('panel.inicio'))->name('inicio');
    // Historias del rol Auditor: sus rutas van acá
});
