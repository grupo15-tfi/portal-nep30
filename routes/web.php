<?php

use App\Http\Controllers\OfertaController;
use App\Http\Controllers\Profesor\MisMateriasController;
use App\Http\Controllers\SedesController;
use App\Http\Controllers\SedesMapaController;
use App\Http\Controllers\SitioController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ---------- HU01 (#11): sitio público (sin iniciar sesión) ----------
Route::get('/', [SitioController::class, 'inicio'])->name('inicio');
Route::get('/historia', [SitioController::class, 'seccion'])->defaults('clave', 'historia')->name('historia');
Route::get('/contacto', [SitioController::class, 'seccion'])->defaults('clave', 'contacto')->name('contacto');
Route::get('/privacidad', [SitioController::class, 'seccion'])->defaults('clave', 'privacidad')->name('privacidad');
Route::get('/noticias', [SitioController::class, 'noticias'])->name('noticias.index');
Route::get('/noticias/{slug}', [SitioController::class, 'noticia'])->name('noticias.show');

// ---------- HU02 (#44): oferta educativa por sede y calendario académico (públicas) ----------
Route::get('/oferta', [OfertaController::class, 'index'])->name('oferta');
Route::get('/oferta/{curso}', [OfertaController::class, 'curso'])->whereNumber('curso')->name('oferta.curso');
Route::get('/calendario', [OfertaController::class, 'calendario'])->name('calendario');

// ---------- HU04 (#52): formulario de preinscripción (público). Lo guarda Preinscribir de #51. ----------
Route::get('/preinscripcion/{curso}', [OfertaController::class, 'preinscripcion'])->whereNumber('curso')->name('preinscripcion.crear');

// ---------- HU03 (#49): página del mapa de sedes (pública) ----------
Route::get('/sedes', SedesMapaController::class)->name('sedes.mapa');

// ---------- HU03 (#48): datos de sedes y sede más cercana (públicas, JSON para el mapa) ----------
// throttle:30,1 = como máximo 30 pedidos por minuto desde la misma IP
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/sedes/datos', [SedesController::class, 'datos'])->name('sedes.datos');
    Route::get('/sedes/cercanas', [SedesController::class, 'cercanas'])->name('sedes.cercanas');
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

// HU06 (#16): perfil propio, para los cuatro roles (solo con sesión iniciada)
Route::view('/perfil', 'perfil')->middleware('auth')->name('perfil');

// Paneles por rol: cada grupo exige sesión iniciada y el rol correspondiente.
// Las rutas nuevas de cada rol van dentro de su grupo (URL /<prefijo>/..., nombre <rol>.<ruta>).

Route::middleware(['auth', 'role:administrador'])->prefix('admin')->name('admin.')->group(function () {
    
    // HU07 (#9): rutas de sedes, cursos y planes van acá

    Route::get('/inicio', fn () => view('panel.inicio'))->name('inicio');
    Route::view('/cursos', 'admin.cursos')->name('cursos');
    Route::view('/planes', 'admin.planes')->name('planes');
    Route::view('/sedes', 'admin.sedes')->name('sedes');
    // HU08 (#14): gestión de profesores
    Route::view('/profesores', 'admin.profesores')->name('profesores.index');
    Route::get('/profesores/{profesor}/asignaciones', fn (User $profesor) => view('admin.profesores-asignaciones', ['profesor' => $profesor]))
        ->name('profesores.asignaciones');
    
});

Route::middleware(['auth', 'role:profesor'])->prefix('profesor')->name('profesor.')->group(function () {
    Route::get('/inicio', fn () => view('panel.inicio'))->name('inicio');
    // HU08 (#54): las materias que tiene asignadas
    Route::get('/materias', MisMateriasController::class)->name('materias');
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

// HU06 (#15): recuperación y creación de contraseña (sin sesión)
Route::view('/olvide-mi-contrasena', 'auth.olvide-contrasena')->middleware('guest')->name('password.request');
Route::get('/restablecer/{token}', fn (string $token) => view('auth.restablecer-contrasena', ['token' => $token]))
    ->middleware('guest')->name('password.reset');
