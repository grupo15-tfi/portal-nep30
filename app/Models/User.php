<?php

namespace App\Models;
use App\Models\Concerns\RecuperaContrasena;  
use App\Models\Concerns\EsProfesor; 
use App\Models\Concerns\TieneTelefonos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema: Administrador, Profesor, Alumno o Auditor (roles de Spatie).
 * Se ingresa con correo y contraseña (HU05). La baja es lógica (deleted_at):
 * el historial académico no se pierde.
 */
#[Fillable(['dni', 'apellido', 'nombre', 'email', 'password', 'sede_id', 'fecha_nacimiento', 'direccion', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;
    use TieneTelefonos;
    use RecuperaContrasena;  
    use EsProfesor;
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function nombreCompleto(): string
    {
        return "{$this->apellido}, {$this->nombre}";
    }
}
