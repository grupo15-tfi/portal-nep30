<?php

namespace App\Actions\Contenidos;
use Illuminate\Support\Facades\Auth;
use App\Models\Evento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * HU10 (#74): eventos institucionales del calendario (curso_id null).
 * Contrato (#47): ejecutar(array $datos, ?Evento $evento = null): Evento
 *   $datos: titulo, descripcion?, tipo, fecha_inicio, fecha_fin?, publico
 * Los públicos aparecen en /calendario (HU02, Evento::publicos()).
 */
class GuardarEvento
{
    public const TIPOS = ['institucional', 'evaluacion', 'entrega', 'feriado', 'otro']; // chk_evento_tipo

    public static function ejecutar(array $datos, ?Evento $evento = null): Evento
    {
        $validos = Validator::make($datos, [
            'titulo'       => ['required', 'string', 'max:150'],
            'descripcion'  => ['nullable', 'string'],
            'tipo'         => ['required', Rule::in(self::TIPOS)],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'publico'      => ['boolean'],
        ], [
            'titulo.required'          => 'Escribí el título del evento.',
            'tipo.required'            => 'Elegí el tipo de evento.',
            'tipo.in'                  => 'Elegí un tipo de la lista.',
            'fecha_inicio.required'    => 'Elegí la fecha de inicio.',
            'fecha_inicio.date'        => 'La fecha de inicio no es válida.',
            'fecha_fin.date'           => 'La fecha de fin no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior al inicio.',
        ])->validate();

        $evento ??= new Evento(['creado_por' => Auth::id()]);

        $evento->fill([
            ...$validos,
            'descripcion' => $validos['descripcion'] ?? null,
            'fecha_fin'   => $validos['fecha_fin'] ?? null,
            'publico'     => (bool) ($validos['publico'] ?? false),
            'curso_id'    => null, // institucional: los de un curso los carga el profesor (otra historia)
        ])->save();

        return $evento;
    }
}