<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h1>Cursos</h1>
        <button type="button" wire:click="nuevo" class="bg-primario text-white hover:bg-primario/90">+ Nuevo curso</button>
    </div>

    @if (session('ok')) <p role="status">{{ session('ok') }}</p> @endif
    @if (session('error')) <p role="alert">{{ session('error') }}</p> @endif

    {{-- Listado con el cupo disponible (vista v_cupos_disponibles) --}}
    <table>
        <thead>
            <tr>
                <th>Plan</th>
                <th>Sede</th>
                <th>Período</th>
                <th>Turno y horario</th>
                <th>Cupo</th>
                <th>Inscriptos</th>
                <th>Disponibles</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cursos as $curso)
                <tr wire:key="curso-{{ $curso->id }}">
                    <td>{{ $curso->planEstudio->nombre }}</td>
                    <td>{{ $curso->sede->nombre }}</td>
                    <td>{{ $curso->periodoLectivo->anio }}{{ $curso->periodoLectivo->cuatrimestre ? ' · ' . $curso->periodoLectivo->cuatrimestre . '.º cuat.' : '' }}</td>
                    <td>
                        {{ ucfirst($curso->turno) }}
                        · {{ substr($curso->hora_inicio, 0, 5) }} a {{ substr($curso->hora_fin, 0, 5) }}
                        @if ($curso->dias) ({{ $curso->dias }}) @endif
                    </td>
                    <td>{{ $curso->cupo }}</td>
                    <td>{{ $curso->inscriptos }}</td>
                    <td>{{ max(0, (int) $curso->disponibles) }}{{ $curso->disponibles <= 0 ? ' (lista de espera)' : '' }}</td>
                    <td>{{ ucfirst($curso->estado) }}</td>
                    <td>
                        <button type="button" wire:click="editar({{ $curso->id }})">Editar</button>
                        @if ($curso->estado !== 'finalizado')
                            <button type="button" wire:click="cambiarEstado({{ $curso->id }})"
                                    wire:confirm="¿Seguro que querés {{ $curso->estado === 'activo' ? 'desactivar' : 'activar' }} este curso?">
                                {{ $curso->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">Todavía no hay cursos cargados.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- #73: alta y edición en la ventana emergente --}}
    @if ($ventanaAbierta)
        <x-ventana id="titulo-curso" :titulo="$cursoId ? 'Editar curso' : 'Nuevo curso'" cerrar="limpiar">
            <form wire:submit="guardar">
                <div>
                    <label for="planId">Plan *</label>
                    <select id="planId" wire:model="planId">
                        <option value="">Elegí un plan</option>
                        @foreach ($planes as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->nombre }}</option>
                        @endforeach
                    </select>
                    @error('planId') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="sedeId">Sede *</label>
                    <select id="sedeId" wire:model="sedeId">
                        <option value="">Elegí una sede</option>
                        @foreach ($sedes as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sedeId') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="periodoId">Período *</label>
                    <select id="periodoId" wire:model="periodoId">
                        <option value="">Elegí un período</option>
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo->id }}">
                                {{ $periodo->anio }}{{ $periodo->cuatrimestre ? ' · ' . $periodo->cuatrimestre . '.º cuatrimestre' : ' · anual' }}
                            </option>
                        @endforeach
                    </select>
                    @error('periodoId') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="turno">Turno *</label>
                    <select id="turno" wire:model="turno">
                        <option value="mañana">Mañana</option>
                        <option value="tarde">Tarde</option>
                        <option value="noche">Noche</option>
                    </select>
                    @error('turno') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="dias">Días</label>
                    <input id="dias" type="text" wire:model="dias" maxlength="60" placeholder="Lunes a viernes">
                    @error('dias') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="horaInicio">Hora de inicio *</label>
                    <input id="horaInicio" type="time" wire:model="horaInicio">
                    @error('horaInicio') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="horaFin">Hora de fin *</label>
                    <input id="horaFin" type="time" wire:model="horaFin">
                    @error('horaFin') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cupo">Cupo * (1 a 500)</label>
                    <input id="cupo" type="number" min="1" max="500" wire:model="cupo">
                    @error('cupo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar">{{ $cursoId ? 'Guardar cambios' : 'Crear curso' }}</button>
                    <button type="button" wire:click="limpiar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>