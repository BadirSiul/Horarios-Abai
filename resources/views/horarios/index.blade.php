@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4 text-center">📅 Horarios de Asesores</h2>

    {{-- Mensaje de éxito --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show text-center" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Mensaje de error --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Formulario de carga --}}
    <form action="{{ route('horarios.importar') }}" method="POST" enctype="multipart/form-data" class="mb-4">
        @csrf
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">📤 Importar Archivo Excel</h5>
                        <div class="mb-3">
                            <label for="archivo" class="form-label">Seleccionar archivo:</label>
                            <input type="file" name="archivo" id="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="limpiar_anteriores" id="limpiar_anteriores" value="1">
                            <label class="form-check-label" for="limpiar_anteriores">
                                <strong>🗑️ Limpiar datos anteriores antes de importar</strong>
                                <br>
                                <small class="text-muted">(Eliminará todos los horarios existentes y los reemplazará con los nuevos datos)</small>
                            </label>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                📤 Importar Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Botón para limpiar datos manualmente --}}
    @if(\App\Models\Horario::count() > 0)
    <div class="mb-4 text-center">
        <form action="{{ route('horarios.limpiar') }}" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar TODOS los horarios? Esta acción no se puede deshacer.');">
        @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                🗑️ Limpiar Todos los Horarios
            </button>
        </form>
    </div>
    @endif

    {{-- Filtro por Team Leader --}}
    <form method="GET" action="{{ route('horarios.index') }}" class="mb-4">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <label for="team_leader" class="form-label"><strong>Filtrar por Team Leader:</strong></label>
                <select name="team_leader" id="team_leader" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos los Team Leaders</option>
                    @foreach($teamLeaders as $leader)
                        <option value="{{ $leader }}" {{ request('team_leader') == $leader ? 'selected' : '' }}>
                            {{ $leader }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(request('team_leader'))
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('horarios.index') }}" class="btn btn-secondary">Limpiar filtro</a>
                </div>
            @endif
        </div>
    </form>

    {{-- Resumen agrupado por empleado y rango de fechas --}}
    @if(isset($horariosAgrupados) && $horariosAgrupados->count() > 0)
    <div class="mb-4">
        <h4 class="mb-3">📊 Resumen por Empleado (Agrupado por Rango de Fechas)</h4>
        <div class="row">
            @foreach($horariosAgrupados as $grupo)
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <strong>{{ $grupo['empleado']->nombre ?? 'N/A' }}</strong>
                            <span class="badge bg-light text-dark ms-2">{{ $grupo['empleado']->coordinador ?? 'Sin Team Leader' }}</span>
                        </div>
                        <div class="card-body">
                            {{-- Información básica --}}
                            <div class="mb-3">
                                <p class="mb-1">
                                    <strong>📅 Rango de fechas:</strong> 
                                    @if($grupo['fecha_inicio'] && $grupo['fecha_fin'])
                                        @if($grupo['fecha_inicio'] === $grupo['fecha_fin'])
                                            {{ \Carbon\Carbon::parse($grupo['fecha_inicio'])->format('d/m/Y') }}
                                        @else
                                            {{ \Carbon\Carbon::parse($grupo['fecha_inicio'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($grupo['fecha_fin'])->format('d/m/Y') }}
                                        @endif
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </p>
                                <p class="mb-1">
                                    <strong>📊 Total de días:</strong> <span class="badge bg-info">{{ $grupo['total_dias'] }}</span>
                                </p>
                                <p class="mb-0">
                                    <strong>😴 Días de descanso:</strong> 
                                    @if(isset($grupo['dias_descanso']) && $grupo['dias_descanso']->count() > 0)
                                        <span class="badge bg-secondary">
                                            {{ $grupo['dias_descanso']->join(', ') }}
                                        </span>
                                    @else
                                        <span class="badge bg-success">Sin días de descanso registrados</span>
                                    @endif
                                </p>
                            </div>

                            <hr>

                            {{-- Horarios principales --}}
                            <div class="mb-3">
                                <h6 class="text-primary mb-2">⏰ Horarios del Turno</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Inicio Turno</small>
                                            <strong class="text-success">
                                                @if($grupo['horario_comun']['inicio_turno'])
                                                    {{ $grupo['horario_comun']['inicio_turno'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Fin Turno</small>
                                            <strong class="text-danger">
                                                @if($grupo['horario_comun']['fin_turno'])
                                                    {{ $grupo['horario_comun']['fin_turno'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Horarios de descanso --}}
                            <div class="mb-3">
                                <h6 class="text-info mb-2">☕ Horarios de Descanso</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Inicio Descanso</small>
                                            <strong>
                                                @if($grupo['horario_comun']['inicio_descanso'])
                                                    {{ $grupo['horario_comun']['inicio_descanso'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Fin Descanso</small>
                                            <strong>
                                                @if($grupo['horario_comun']['fin_descanso'])
                                                    {{ $grupo['horario_comun']['fin_descanso'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Horarios de almuerzo --}}
                            <div class="mb-3">
                                <h6 class="text-warning mb-2">🍽️ Horarios de Almuerzo</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Inicio Almuerzo</small>
                                            <strong class="text-warning">
                                                @if($grupo['horario_comun']['inicio_almuerzo'])
                                                    {{ $grupo['horario_comun']['inicio_almuerzo'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 bg-light">
                                            <small class="text-muted d-block">Fin Almuerzo</small>
                                            <strong class="text-warning">
                                                @if($grupo['horario_comun']['fin_almuerzo'])
                                                    {{ $grupo['horario_comun']['fin_almuerzo'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Horas programadas --}}
                            @if($grupo['horario_comun']['horas_prog'])
                            <div class="mb-3">
                                <div class="alert alert-info mb-0 py-2">
                                    <strong>⏱️ Horas Programadas:</strong> 
                                    <span class="badge bg-primary fs-6">
                                        @php
                                            $hrs = $grupo['horario_comun']['horas_prog'];
                                            if (is_numeric($hrs) && $hrs < 1 && $hrs > 0) {
                                                $totalMinutes = round($hrs * 1440);
                                                $hours = floor($totalMinutes / 60);
                                                $minutes = $totalMinutes % 60;
                                                echo sprintf('%d:%02d', $hours, $minutes);
                                            } else {
                                                echo $hrs;
                                            }
                                        @endphp
                                    </span>
                                </div>
                            </div>
                            @endif

                            <hr>

                            {{-- Botón para ver detalles completos --}}
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#detalle-{{ $grupo['empleado']->id }}" aria-expanded="false">
                                    📋 Ver Detalles Completos por Día
                                </button>
                            </div>
                            <div class="collapse mt-3" id="detalle-{{ $grupo['empleado']->id }}">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Día</th>
                                                <th>Inicio Turno</th>
                                                <th>Fin Turno</th>
                                                <th>Inicio Descanso</th>
                                                <th>Fin Descanso</th>
                                                <th>Inicio Almuerzo</th>
                                                <th>Fin Almuerzo</th>
                                                <th>Horas Prog</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($grupo['horarios'] as $h)
                                                <tr>
                                                    <td><strong>{{ \Carbon\Carbon::parse($h->fecha)->format('d/m/Y') }}</strong></td>
                                                    <td>
                                                        @if($h->dia && !is_numeric($h->dia))
                                                            <span class="badge bg-secondary">{{ ucfirst($h->dia) }}</span>
                                                        @elseif($h->fecha)
                                                            <span class="badge bg-secondary">{{ ucfirst(\Carbon\Carbon::parse($h->fecha)->locale('es')->dayName) }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($h->hi && $h->hi !== '00:00')
                                                            <span class="badge bg-success">{{ $h->hi }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($h->hf && $h->hf !== '00:00')
                                                            <span class="badge bg-danger">{{ $h->hf }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $h->hid1 && $h->hid1 !== '00:00' ? $h->hid1 : '-' }}</td>
                                                    <td>{{ $h->hfd1 && $h->hfd1 !== '00:00' ? $h->hfd1 : '-' }}</td>
                                                    <td>
                                                        @if($h->hia && $h->hia !== '00:00')
                                                            <span class="badge bg-warning text-dark">{{ $h->hia }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($h->hfa && $h->hfa !== '00:00')
                                                            <span class="badge bg-warning text-dark">{{ $h->hfa }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($h->hrs_prog)
                                                            @php
                                                                if (is_numeric($h->hrs_prog) && $h->hrs_prog < 1 && $h->hrs_prog > 0) {
                                                                    $totalMinutes = round($h->hrs_prog * 1440);
                                                                    $hours = floor($totalMinutes / 60);
                                                                    $minutes = $totalMinutes % 60;
                                                                    $horasFormato = sprintf('%d:%02d', $hours, $minutes);
                                                                } else {
                                                                    $horasFormato = $h->hrs_prog;
                                                                }
                                                            @endphp
                                                            <strong>{{ $horasFormato }}</strong>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

   