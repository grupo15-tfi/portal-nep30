{{--
    HU04 (#52): formulario de preinscripción y confirmación. Diseño: pantallas 04 y 04b (#35).
    Cada campo tiene su error debajo (@error con la clave del contrato #46) y aria-describedby.
--}}
@php
    use App\Livewire\Publico\Preinscripcion;
    $campo = 'rounded border border-gray-500 bg-white px-3 py-2 w-full';
    $conError = 'border-error ring-1 ring-error';
@endphp

<div>
    {{-- Pasos: 1. Curso · 2. Tus datos · 3. Listo --}}
    <ol class="mb-6 grid grid-cols-3 gap-2 list-none p-0 text-sm font-semibold" aria-label="Pasos de la preinscripción">
        <li class="rounded bg-green-100 px-3 py-2 text-green-600">1. Curso</li>
        <li @class(['rounded px-3 py-2', 'bg-primario text-white' => ! $resultado, 'bg-green-100 text-green-600' => $resultado])
            @if (! $resultado) aria-current="step" @endif>2. Tus datos</li>
        <li @class(['rounded px-3 py-2', 'bg-primario text-white' => $resultado, 'bg-gray-100 text-texto-suave' => ! $resultado])
            @if ($resultado) aria-current="step" @endif>3. Listo</li>
    </ol>

    @if ($resultado)
        {{-- Confirmación --}}
        <section aria-labelledby="titulo-listo" class="max-w-3xl rounded-lg border border-borde bg-white p-5 md:p-8" wire:key="listo">
            @if ($resultado['en_espera'])
                <p class="mb-3 inline-flex size-12 items-center justify-center rounded-full bg-amber-100 text-2xl font-bold text-amber-700" aria-hidden="true">!</p>
                <h2 id="titulo-listo" class="mt-0 mb-2 font-titulos text-2xl md:text-3xl">Quedaste en la lista de espera</h2>
                <p class="text-lg mb-2">
                    Estás en el lugar <strong class="text-amber-700">N.º {{ $resultado['lugar'] }}</strong>
                    de la lista de {{ $cursoObj->plan }} · Turno {{ $cursoObj->turno }}.
                </p>
                <p class="text-texto-suave mb-3">Cuando se libere un cupo te llamamos al teléfono que dejaste. La lista se respeta por orden de llegada.</p>
            @else
                <p class="mb-3 inline-flex size-12 items-center justify-center rounded-full bg-green-100 text-2xl font-bold text-green-600" aria-hidden="true">✓</p>
                <h2 id="titulo-listo" class="mt-0 mb-2 font-titulos text-2xl md:text-3xl">Recibimos tu preinscripción</h2>
                <p class="text-lg mb-2">Te anotaste en {{ $cursoObj->plan }} · Turno {{ $cursoObj->turno }} · {{ $cursoObj->sede }}.</p>
                <p class="text-texto-suave mb-3">La secretaría la revisa y te avisa por correo. Para completar la inscripción llevá la fotocopia del DNI a la sede.</p>
            @endif

            <p class="text-sm text-texto-suave mb-5">
                Código de seguimiento: <strong class="text-texto">{{ $resultado['codigo'] }}</strong>
                · También te lo mandamos a {{ $resultado['email_oculto'] }}
            </p>
            <p class="flex flex-col gap-3 sm:flex-row mb-0">
                <a href="{{ route('oferta') }}" class="inline-flex min-h-11 items-center justify-center rounded bg-primario px-5 py-2.5 font-semibold text-white hover:bg-primario/90">Ver otros cursos</a>
                <a href="{{ route('inicio') }}" class="inline-flex min-h-11 items-center justify-center rounded border-2 border-primario px-5 py-2 font-semibold text-primario hover:bg-primario-claro">Volver al inicio</a>
            </p>
        </section>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            {{-- Curso elegido (en el celular va arriba del formulario) --}}
            <aside aria-labelledby="titulo-curso" class="rounded-lg border border-borde bg-white p-4 lg:order-2">
                <p class="mb-1 text-sm font-bold uppercase tracking-wider text-primario">Curso elegido</p>
                <h2 id="titulo-curso" class="mt-0 mb-1 text-xl font-semibold text-texto font-sans">{{ $cursoObj->plan }}</h2>
                <p class="m-0 text-texto-suave">{{ $cursoObj->sede }}</p>
                <p class="m-0 text-texto-suave">{{ $cursada }}</p>
                <p class="mt-2 mb-3"><a href="{{ route('oferta') }}" class="font-semibold text-primario underline">Cambiar curso</a></p>

                @if ($sinCupo)
                    <div class="mb-3 rounded border-l-4 border-amber-700 bg-amber-100 p-3">
                        <p class="m-0 font-semibold text-amber-700">Este curso no tiene cupo</p>
                        <p class="m-0 text-sm text-texto">Si enviás la preinscripción quedás en la lista de espera por orden de llegada. Te llamamos cuando se libere un lugar.</p>
                    </div>
                @endif

                <div class="rounded bg-gray-100 p-3 text-sm">
                    <p class="m-0 font-semibold">Para inscribirte vas a necesitar</p>
                    <p class="m-0 text-texto-suave">Fotocopia del DNI. Tener {{ $cursoObj->edad_minima }} años o más.</p>
                </div>
            </aside>

            <form wire:submit="enviar" novalidate class="lg:order-1" aria-describedby="campos-obligatorios">
                @if ($errors->any())
                    <div role="alert" class="mb-5 max-w-none rounded border-l-4 border-error bg-red-100 p-3">
                        <p class="m-0 font-semibold text-error">{{ $errors->count() === 1 ? 'Revisá 1 campo' : 'Revisá '.$errors->count().' campos' }}</p>
                        <p class="m-0 text-texto">Corregí los datos marcados en rojo para continuar.</p>
                    </div>
                @endif
                <p id="campos-obligatorios" class="text-sm text-texto-suave">Los campos con * son obligatorios.</p>

                <fieldset class="mb-6 max-w-none border-0 p-0">
                    <legend class="mb-3 font-titulos text-xl font-semibold text-primario">Datos personales</legend>
                    <div class="grid gap-4 sm:grid-cols-2 max-w-none mb-0">
                        @foreach ([
                            ['apellido', 'Apellido *', 'text', 'family-name', 100, null],
                            ['nombre', 'Nombre *', 'text', 'given-name', 100, null],
                            ['dni', 'DNI *', 'text', 'off', 10, 'Solo números, con o sin puntos.'],
                            ['fecha_nacimiento', 'Fecha de nacimiento *', 'date', 'bday', null, null],
                        ] as [$id, $etiqueta, $tipo, $auto, $max, $ayuda])
                            <div class="flex flex-col gap-1 mb-0">
                                <label for="{{ $id }}" class="font-semibold">{{ $etiqueta }}</label>
                                <input id="{{ $id }}" type="{{ $tipo }}" wire:model="{{ $id }}" autocomplete="{{ $auto }}"
                                       @if ($max) maxlength="{{ $max }}" @endif
                                       @if ($id === 'dni') inputmode="numeric" @endif
                                       @if ($id === 'fecha_nacimiento') max="{{ now()->toDateString() }}" @endif
                                       aria-describedby="{{ $ayuda ? 'ayuda-'.$id.' ' : '' }}error-{{ $id }}"
                                       @error($id) aria-invalid="true" @enderror
                                       @class([$campo, $conError => $errors->has($id)])>
                                @if ($ayuda) <p id="ayuda-{{ $id }}" class="m-0 text-sm text-texto-suave">{{ $ayuda }}</p> @endif
                                @error($id) <p id="error-{{ $id }}" class="m-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="mb-6 max-w-none border-0 p-0">
                    <legend class="mb-3 font-titulos text-xl font-semibold text-primario">Contacto</legend>
                    <div class="grid gap-4 sm:grid-cols-2 max-w-none mb-0">
                        <div class="flex flex-col gap-1 mb-0">
                            <label for="telefono" class="font-semibold">Teléfono *</label>
                            <input id="telefono" type="tel" wire:model="telefono" autocomplete="tel" maxlength="30" placeholder="Ej.: 370 4123456"
                                   aria-describedby="ayuda-telefono error-telefono" @error('telefono') aria-invalid="true" @enderror
                                   @class([$campo, $conError => $errors->has('telefono')])>
                            <p id="ayuda-telefono" class="m-0 text-sm text-texto-suave">Obligatorio: te llamamos si se libera un cupo.</p>
                            @error('telefono') <p id="error-telefono" class="m-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-1 mb-0">
                            <label for="email" class="font-semibold">Correo electrónico *</label>
                            <input id="email" type="email" wire:model="email" autocomplete="email" maxlength="150" placeholder="Ej.: nombre@gmail.com"
                                   aria-describedby="error-email" @error('email') aria-invalid="true" @enderror
                                   @class([$campo, $conError => $errors->has('email')])>
                            @error('email') <p id="error-email" class="m-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-1 mb-0 sm:col-span-2">
                            <label for="domicilio" class="font-semibold">Domicilio (opcional)</label>
                            <input id="domicilio" type="text" wire:model="domicilio" autocomplete="street-address" maxlength="200" placeholder="Calle y número o manzana y casa"
                                   aria-describedby="error-domicilio" @class([$campo, $conError => $errors->has('domicilio')])>
                            @error('domicilio') <p id="error-domicilio" class="m-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="mb-6 max-w-none border-0 p-0">
                    <legend class="mb-3 font-titulos text-xl font-semibold text-primario">Estudios</legend>
                    <div class="grid gap-4 sm:grid-cols-2 max-w-none mb-0">
                        <div class="flex flex-col gap-1 mb-0">
                            <label for="nivel_educativo" class="font-semibold">Nivel educativo (opcional)</label>
                            <select id="nivel_educativo" wire:model="nivel_educativo" aria-describedby="error-nivel_educativo"
                                    @class([$campo, $conError => $errors->has('nivel_educativo')])>
                                <option value="">Elegí una opción</option>
                                @foreach (Preinscripcion::NIVELES as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </select>
                            @error('nivel_educativo') <p id="error-nivel_educativo" class="m-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                        </div>
                        <div class="flex flex-col gap-1 mb-0">
                            <label for="ultimo_ciclo_aprobado" class="font-semibold">Último grado aprobado de la primaria (opcional)</label>
                            <select id="ultimo_ciclo_aprobado" wire:model="ultimo_ciclo_aprobado" aria-describedby="error-ultimo_ciclo_aprobado"
                                    @class([$campo, $conError => $errors->has('ultimo_ciclo_aprobado')])>
                                <option value="">Elegí una opción</option>
                                @foreach (range(1, 6) as $grado)
                                    <option value="{{ $grado }}">{{ $grado }}.º grado</option>
                                @endforeach
                            </select>
                            @error('ultimo_ciclo_aprobado') <p id="error-ultimo_ciclo_aprobado" class="m-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                <div class="mb-6 max-w-none">
                    <label for="acepta_privacidad" class="flex items-start gap-3">
                        <input id="acepta_privacidad" type="checkbox" wire:model="acepta_privacidad" class="mt-1 size-5 shrink-0 accent-primario"
                               aria-describedby="error-acepta_privacidad" @error('acepta_privacidad') aria-invalid="true" @enderror>
                        <span>Leí y acepto la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener" class="text-primario underline">política de privacidad<span class="sr-only"> (se abre en otra pestaña)</span></a>. Mis datos se usan solo para la inscripción (Ley 25.326). *</span>
                    </label>
                    @error('acepta_privacidad') <p id="error-acepta_privacidad" class="mt-1 mb-0 text-sm font-semibold text-error">⚠ {{ $message }}</p> @enderror
                    @error('curso_id') <p class="mt-3 mb-0 rounded bg-red-100 p-3 font-semibold text-error">⚠ {{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end max-w-none mb-0">
                    <a href="{{ route('oferta.curso', $cursoObj->curso_id) }}" class="inline-flex min-h-11 items-center justify-center rounded border-2 border-primario px-5 py-2 font-semibold text-primario hover:bg-primario-claro">Volver</a>
                    <button type="submit" class="min-h-11 px-5 py-2.5 disabled:opacity-60 disabled:cursor-wait" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="enviar">{{ $sinCupo ? 'Anotarme en la lista de espera' : 'Enviar preinscripción' }}</span>
                        <span wire:loading wire:target="enviar">Enviando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
