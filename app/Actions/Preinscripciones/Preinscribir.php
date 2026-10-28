<?php

namespace App\Actions\Preinscripciones;

use App\Events\PreinscripcionRecibida;
use App\Models\Preinscripcion;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HU04 (#51): guarda la preinscripción de un aspirante (RF06, RF09).
 * Contrato (#46): ejecutar(array $datos): Preinscripcion
 *   $datos: curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono,
 *           domicilio?, nivel_educativo?, ultimo_ciclo_aprobado?, acepta_privacidad
 *   Errores: ValidationException con esas mismas claves (el front las muestra con @error).
 *   Devuelve la preinscripción con su estado: pendiente o en_espera (lo decide la base).
 *
 * No crea usuario. Guarda solo los campos de la lista (Ley 25.326): si llega otro dato, se ignora.
 */
class Preinscribir
{
    public const DUPLICADA = 'Ya tenés una preinscripción pendiente para este curso.';

    public static function ejecutar(array $datos): Preinscripcion
    {
        // El DNI se acepta con puntos o espacios («12.345.678») y se guarda solo con números
        if (isset($datos['dni']) && is_string($datos['dni'])) {
            $datos['dni'] = preg_replace('/[^0-9]/', '', $datos['dni']);   
        }

        $validos = Validator::make($datos, [
            // Solo cursos de la oferta pública: activos, de plan, sede y período activos
            'curso_id'              => ['required', 'integer', Rule::exists('v_oferta_publica', 'curso_id')],
            'dni'                   => ['required', 'regex:/^[0-9]{7,8}$/'],
            'apellido'              => ['required', 'string', 'max:100'],
            'nombre'                => ['required', 'string', 'max:100'],
            'fecha_nacimiento'      => ['required', 'date', 'after_or_equal:1900-01-01', 'before:today'],
            'email'                 => ['required', 'email', 'max:150'],
            'telefono'              => ['required', 'regex:/^[0-9 +()-]{6,30}$/'],
            'domicilio'             => ['nullable', 'string', 'max:200'],
            'nivel_educativo'       => ['nullable', Rule::in(['sin_escolaridad', 'primaria_incompleta', 'primaria_completa',
                'secundaria_incompleta', 'secundaria_completa', 'superior'])],
            'ultimo_ciclo_aprobado' => ['nullable', 'integer', 'between:1,6'],
            'acepta_privacidad'     => ['accepted'],
        ], [
            'curso_id.required'         => 'Elegí un curso.',
            'curso_id.exists'           => 'Ese curso no está disponible para preinscripción.',
            'dni.required'              => 'Escribí tu DNI.',
            'dni.regex'                 => 'El DNI tiene que tener 7 u 8 números.',
            'apellido.required'         => 'Escribí tu apellido.',
            'nombre.required'           => 'Escribí tu nombre.',
            'fecha_nacimiento.required' => 'Escribí tu fecha de nacimiento.',
            'fecha_nacimiento.date'     => 'La fecha de nacimiento no es válida.',
            'fecha_nacimiento.*'        => 'La fecha de nacimiento no es válida.',
            'email.required'            => 'Escribí tu correo.',
            'email.email'               => 'El correo no es válido.',
            'telefono.required'         => 'Escribí un teléfono de contacto.',
            'telefono.regex'            => 'El teléfono solo puede tener números, espacios y los signos + ( ) -.',
            'nivel_educativo.in'        => 'Elegí un nivel educativo de la lista.',
            'ultimo_ciclo_aprobado.*'   => 'El último ciclo aprobado tiene que ser un número del 1 al 6.',
            'acepta_privacidad.accepted' => 'Tenés que aceptar la política de privacidad para enviar la preinscripción.',
            '*.max'                     => 'Es demasiado largo.',
        ])->validate();

        self::controlarEdad($validos);

        try {
            $preinscripcion = Preinscripcion::create([
                ...$validos,
                'email'             => mb_strtolower($validos['email']),
                'acepta_privacidad' => true,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(self::errorDeLaBase($e));
        }

        // El trigger pudo cambiar el estado a en_espera: se vuelve a leer de la base
        $preinscripcion->refresh();

        PreinscripcionRecibida::dispatch($preinscripcion);

        return $preinscripcion;
    }

    /** Edad mínima del plan del curso. La base también la controla; acá el mensaje sale más claro. */
    private static function controlarEdad(array $validos): void
    {
        $edadMinima = (int) DB::table('v_oferta_publica')
            ->where('curso_id', $validos['curso_id'])
            ->value('edad_minima');

        if (Carbon::parse($validos['fecha_nacimiento'])->age < $edadMinima) {
            throw ValidationException::withMessages([
                'fecha_nacimiento' => "Para este curso tenés que tener al menos {$edadMinima} años.",
            ]);
        }
    }

    /** Traduce los errores de la base a errores del formulario */
    private static function errorDeLaBase(QueryException $e): array
    {
        $codigo = $e->errorInfo[1] ?? null;   // código de MySQL / MariaDB
        $estado = $e->errorInfo[0] ?? null;   // SQLSTATE
        $texto = $e->errorInfo[2] ?? '';

        // Índice uq_preinsc_abierta: ya hay una pendiente o en espera con ese DNI en ese curso
        if ($codigo === 1062) {
            return ['dni' => self::DUPLICADA];
        }

        // Errores del trigger trg_preinsc_bi
        if ($estado === '45000') {
            return str_contains($texto, 'edad')
                ? ['fecha_nacimiento' => $texto]
                : ['curso_id' => $texto];
        }

        throw $e; // otro error: que se vea, no se esconde
    }
}