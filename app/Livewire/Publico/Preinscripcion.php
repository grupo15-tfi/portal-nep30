<?php

namespace App\Livewire\Publico;

use App\Actions\Preinscripciones\Preinscribir;
use App\Models\Preinscripcion as Solicitud;
use App\Support\OfertaTexto;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * HU04 (#52): formulario de preinscripción y pantalla de confirmación (GET /preinscripcion/{curso}).
 * Guarda con Preinscribir::ejecutar() de #51. Las propiedades se llaman igual que las claves del
 * contrato (#46), así cada error de ValidationException aparece debajo de su campo con @error.
 * No crea usuario. Si el curso no tiene cupo, la base deja la solicitud en lista de espera.
 */
class Preinscripcion extends Component
{
    /** Fila de v_oferta_publica del curso elegido (como array, para que Livewire la guarde) */
    public array $datosCurso;

    public string $apellido = '';
    public string $nombre = '';
    public string $dni = '';
    public string $fecha_nacimiento = '';
    public string $telefono = '';
    public string $email = '';
    public string $domicilio = '';
    public string $nivel_educativo = '';
    public string $ultimo_ciclo_aprobado = '';
    public bool $acepta_privacidad = false;

    /** #53: campo trampa (honeypot). Está oculto: una persona no lo ve ni lo completa; un bot sí. */
    public string $sitio_web = '';

    /** #53: como máximo 5 envíos cada 10 minutos desde la misma IP */
    public const MAX_ENVIOS = 5;
    public const MINUTOS = 10;

    /** Resultado del envío: null mientras se completa el formulario */
    public ?array $resultado = null;

    public const NIVELES = [
        'sin_escolaridad'       => 'Sin escolaridad',
        'primaria_incompleta'   => 'Primario incompleto',
        'primaria_completa'     => 'Primario completo',
        'secundaria_incompleta' => 'Secundario incompleto',
        'secundaria_completa'   => 'Secundario completo',
        'superior'              => 'Terciario o universitario',
    ];

    public function mount(object $curso): void
    {
        $this->datosCurso = (array) $curso;
    }

    public function enviar(): void
    {
        // #53: un bot completó el campo trampa → no se guarda nada y no se le da pista
        if ($this->sitio_web !== '') {
            Log::info('Preinscripción descartada por el campo trampa');

            return;
        }

        // #53: límite de envíos por IP. Se cuenta cada intento, salga bien o mal.
        $clave = 'preinscripcion:'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, self::MAX_ENVIOS)) {
            $minutos = (int) ceil(RateLimiter::availableIn($clave) / 60);
            throw ValidationException::withMessages([
                'envio' => "Hiciste demasiados intentos. Probá de nuevo en {$minutos} minuto(s).",
            ]);
        }
        RateLimiter::hit($clave, self::MINUTOS * 60);
        
        $solicitud = Preinscribir::ejecutar([
            'curso_id'              => (int) $this->datosCurso['curso_id'],
            'dni'                   => trim($this->dni),
            'apellido'              => trim($this->apellido),
            'nombre'                => trim($this->nombre),
            'fecha_nacimiento'      => $this->fecha_nacimiento,
            'email'                 => trim($this->email),
            'telefono'              => trim($this->telefono),
            'domicilio'             => trim($this->domicilio) ?: null,
            'nivel_educativo'       => $this->nivel_educativo ?: null,
            'ultimo_ciclo_aprobado' => $this->ultimo_ciclo_aprobado !== '' ? $this->ultimo_ciclo_aprobado : null,
            'acepta_privacidad'     => $this->acepta_privacidad,
        ]);

        // Para la confirmación se guarda lo mínimo: nada de DNI ni datos completos en la página
        $this->resultado = [
            'en_espera'    => $solicitud->enEspera(),
            'codigo'       => self::codigo($solicitud),
            'lugar'        => $solicitud->enEspera() ? self::lugarEnEspera($solicitud) : null,
            'email_oculto' => self::ocultarCorreo($solicitud->email),
        ];

        $this->reset(['apellido', 'nombre', 'dni', 'fecha_nacimiento', 'telefono', 'email', 'domicilio',
            'nivel_educativo', 'ultimo_ciclo_aprobado', 'acepta_privacidad']);
    }

    /** «PRE-2026-0007»: el número que el aspirante da en la secretaría */
    public static function codigo(Solicitud $solicitud): string
    {
        return 'PRE-'.$solicitud->created_at->format('Y').'-'.str_pad((string) $solicitud->id, 4, '0', STR_PAD_LEFT);
    }

    /** Lugar en la lista de espera del curso, por orden de llegada */
    public static function lugarEnEspera(Solicitud $solicitud): int
    {
        return Solicitud::where('curso_id', $solicitud->curso_id)
            ->where('estado', 'en_espera')
            ->where('id', '<=', $solicitud->id)
            ->count();
    }

    /** «maria.gomez@gmail.com» → «m***@gmail.com» */
    public static function ocultarCorreo(string $email): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($usuario, 0, 1).'***@'.$dominio;
    }

    public function render()
    {
        $curso = (object) $this->datosCurso;

        return view('livewire.publico.preinscripcion', [
            'cursoObj' => $curso,
            'cursada'  => OfertaTexto::cursada($curso),
            'sinCupo'  => (bool) $curso->con_lista_espera,
        ]);
    }
}
