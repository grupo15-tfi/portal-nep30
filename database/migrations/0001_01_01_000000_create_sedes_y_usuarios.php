<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sedes y usuarios (modelo v4.4, secciones 1 y 2).
 * RF04, RF05, RF10-RF14, RF16, RNF08, RNF12, RNF13.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('tipo', 10);
            $table->string('direccion', 200)->nullable();      // algunos anexos se ubican por manzana y casa
            $table->string('localidad', 100);
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->decimal('latitud', 10, 7);                  // se cargan a mano si Nominatim no la encuentra
            $table->decimal('longitud', 10, 7);
            $table->string('observaciones', 300)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Restricciones::agregar('sedes', [
            'chk_sede_tipo'  => "tipo IN ('central','sede','anexo')",
            'chk_sede_lat'   => 'latitud BETWEEN -90 AND 90',
            'chk_sede_lon'   => 'longitud BETWEEN -180 AND 180',
            'chk_sede_email' => "email IS NULL OR email LIKE '%_@_%._%'",
        ]);

        // Tabla users de Laravel con los datos propios del sistema.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 8)->unique();
            $table->string('apellido', 100);
            $table->string('nombre', 100);
            $table->string('email', 150)->unique();             // se ingresa con correo y contraseña (HU05)
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');                         // siempre hash bcrypt
            $table->unsignedBigInteger('sede_id')->nullable();  // sede de referencia del personal
            $table->date('fecha_nacimiento')->nullable();
            $table->string('direccion', 200)->nullable();
            $table->boolean('activo')->default(true);           // false = dado de baja: no puede ingresar
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();    // 5 intentos fallidos = 15 minutos (RNF12)
            $table->dateTime('ultimo_acceso')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();                              // el historial no se pierde
            $table->index(['apellido', 'nombre'], 'idx_users_nombre');
            $table->foreign('sede_id', 'fk_users_sede')->references('id')->on('sedes');
        });
        Restricciones::agregar('users', [
            'chk_users_dni'    => "dni REGEXP '^[0-9]{7,8}$'",
            'chk_users_email'  => "email LIKE '%_@_%._%'",
            'chk_users_hash'   => 'CHAR_LENGTH(password) >= 60',
            'chk_users_intent' => 'intentos_fallidos <= 10',
            'chk_users_nac'    => "fecha_nacimiento IS NULL OR fecha_nacimiento >= '1900-01-01'",
        ]);

        // Con tipo 'emergencia' + contacto_nombre se cubre el contacto de emergencia.
        Schema::create('telefonos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('numero', 30);
            $table->string('tipo', 12);
            $table->string('contacto_nombre', 120)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->timestamps();
            $table->foreign('usuario_id', 'fk_tel_usuario')->references('id')->on('users')->cascadeOnDelete();
        });
        Restricciones::agregar('telefonos', [
            'chk_tel_tipo'   => "tipo IN ('celular','fijo','whatsapp','emergencia')",
            'chk_tel_numero' => "numero REGEXP '^[0-9 +()-]{6,30}$'",
            'chk_tel_contac' => "tipo <> 'emergencia' OR contacto_nombre IS NOT NULL",
        ]);

        // Recuperación de contraseña: token con hash, vence a los 60 minutos.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 150)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sesiones en la base (SESSION_DRIVER=database).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index('idx_sessions_user');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index('idx_sessions_last');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('telefonos');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sedes');
    }
};
