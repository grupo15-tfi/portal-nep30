<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h1>Planes de estudio</h1>
        <button type="button" wire:click="nuevoPlan" class="bg-primario text-white hover:bg-primario/90">+ Nuevo plan</button>
    </div>

    @if (session('ok')) <p role="status">{{ session('ok') }}</p> @endif

    {{-- Listado de planes --}}
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Materias</th>
                <th>Edad mín.</th>
                <th>Nota / asistencia</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($planes as $plan)
                <tr wire:key="plan-{{ $plan->id }}">
                    <td>{{ $plan->nombre }}</td>
                    <td>{{ $plan->tipo === 'terminalidad' ? 'Terminalidad' : 'Formación profesional' }}</td>
                    <td>{{ $plan->materias_count }}</td>
                    <td>{{ $plan->edad_minima }}</td>
                    <td>{{ $plan->nota_aprobacion }} / {{ $plan->asistencia_minima }} %</td>
                    <td>{{ $plan->activo ? 'Activo' : 'Inactivo' }}</td>
                    <td>
                        <button type="button" wire:click="editarPlan({{ $plan->id }})">Editar</button>
                        <button type="button" wire:click="verMaterias({{ $plan->id }})">Materias</button>
                        <button type="button" wire:click="cambiarEstadoPlan({{ $plan->id }})"
                                wire:confirm="¿Seguro que querés {{ $plan->activo ? 'desactivar' : 'activar' }} este plan?">
                            {{ $plan->activo ? 'Desactivar' : 'Activar' }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Todavía no hay planes cargados.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- #73: alta y edición en la ventana emergente --}}
    @if ($ventanaPlan)
        <x-ventana id="titulo-plan" :titulo="$planId ? 'Editar plan' : 'Nuevo plan'" cerrar="limpiarPlan">
            <form wire:submit="guardarPlan">
                <div>
                    <label for="nombre">Nombre *</label>
                    <input id="nombre" type="text" wire:model="nombre" maxlength="150">
                    @error('nombre') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tipo">Tipo *</label>
                    <select id="tipo" wire:model="tipo">
                        <option value="formacion_profesional">Formación profesional</option>
                        <option value="terminalidad">Terminalidad</option>
                    </select>
                    @error('tipo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" wire:model="descripcion"></textarea>
                    @error('descripcion') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="duracionMeses">Duración (meses)</label>
                    <input id="duracionMeses" type="number" min="1" wire:model="duracionMeses">
                    @error('duracionMeses') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cantidadCiclos">Cantidad de ciclos (1 a 6)</label>
                    <input id="cantidadCiclos" type="number" min="1" max="6" wire:model="cantidadCiclos">
                    @error('cantidadCiclos') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="resolucion">Resolución</label>
                    <input id="resolucion" type="text" wire:model="resolucion" maxlength="100">
                    @error('resolucion') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="edadMinima">Edad mínima *</label>
                    <input id="edadMinima" type="number" min="14" max="99" wire:model="edadMinima">
                    @error('edadMinima') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="notaAprobacion">Nota de aprobación * (1 a 10)</label>
                    <input id="notaAprobacion" type="number" step="0.01" min="1" max="10" wire:model="notaAprobacion">
                    @error('notaAprobacion') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="asistenciaMinima">Asistencia mínima * (%)</label>
                    <input id="asistenciaMinima" type="number" min="0" max="100" wire:model="asistenciaMinima">
                    @error('asistenciaMinima') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardarPlan">{{ $planId ? 'Guardar cambios' : 'Crear plan' }}</button>
                    <button type="button" wire:click="limpiarPlan">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif

    {{-- #73: materias del plan elegido, en su propia ventana emergente --}}
    @if ($elegido)
        <x-ventana id="titulo-materias" :titulo="'Materias de «' . $elegido->nombre . '»'" cerrar="cerrarMaterias" ancho="max-w-3xl">
            @if (session('ok')) <p role="status">{{ session('ok') }}</p> @endif

            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Ciclo</th><th>Carga horaria</th><th>Obligatoria</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($materias as $materia)
                            <tr wire:key="materia-{{ $materia->id }}">
                                <td>{{ $materia->nombre }}</td>
                                <td>{{ $materia->ciclo ?? '—' }}</td>
                                <td>{{ $materia->carga_horaria ? $materia->carga_horaria . ' h' : '—' }}</td>
                                <td>{{ $materia->es_obligatoria ? 'Sí' : 'No' }}</td>
                                <td><button type="button" wire:click="editarMateria({{ $materia->id }})">Editar</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Este plan todavía no tiene materias.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3>{{ $materiaId ? 'Editar materia' : 'Agregar materia' }}</h3>

            <form wire:submit="guardarMateria">
                <div>
                    <label for="materiaNombre">Nombre *</label>
                    <input id="materiaNombre" type="text" wire:model="materiaNombre" maxlength="150">
                    @error('materiaNombre') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="materiaCiclo">Ciclo (1 a 6)</label>
                    <input id="materiaCiclo" type="number" min="1" max="6" wire:model="materiaCiclo">
                    @error('materiaCiclo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="materiaCarga">Carga horaria (horas)</label>
                    <input id="materiaCarga" type="number" min="1" wire:model="materiaCarga">
                    @error('materiaCarga') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label>
                        <input type="checkbox" wire:model="materiaObligatoria"> Obligatoria
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardarMateria">{{ $materiaId ? 'Guardar cambios' : 'Agregar materia' }}</button>
                    @if ($materiaId)
                        <button type="button" wire:click="limpiarMateria">Cancelar edición</button>
                    @endif
                    <button type="button" wire:click="cerrarMaterias">Cerrar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>