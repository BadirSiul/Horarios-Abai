@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="text-center mb-4">Horarios por Team Leader</h2>

    @foreach($data as $teamLeader => $asesores)
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                {{ $teamLeader }}
            </div>
            <div class="card-body">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            
                            <th>Asesor</th>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Transacciones</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Inicio Desc 1</th>
                            <th>Fin Desc 1</th>
                            <th>Inicio Desc 2</th>
                            <th>Fin Desc 2</th>
                            <th>Inicio Desc 3</th>
                            <th>Fin Desc 3</th>
                            <th>Inicio Almuerzo</th>
                            <th>Fin Almuerzo</th>
                            <th>Horas Prog</th>
                            <th>Servicio</th>
                            <th>Cuenta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($asesores as $a)
                            <tr>
                                
                                <td>{{ $a['asesor'] ?? '' }}</td>
                                <td>{{ $a['fecha'] ?? '' }}</td>
                                <td>{{ $a['dia'] ?? '' }}</td>
                                <td>{{ $a['transacciones'] ?? '' }}</td>
                                <td>{{ $a['hi'] ?? '' }}</td>
                                <td>{{ $a['hf'] ?? '' }}</td>
                                <td>{{ $a['hid1'] ?? '' }}</td>
                                <td>{{ $a['hfd1'] ?? '' }}</td>
                                <td>{{ $a['hid2'] ?? '' }}</td>
                                <td>{{ $a['hfd2'] ?? '' }}</td>
                                <td>{{ $a['hid3'] ?? '' }}</td>
                                <td>{{ $a['hfd3'] ?? '' }}</td>
                                <td>{{ $a['hia'] ?? '' }}</td>
                                <td>{{ $a['hfa'] ?? '' }}</td>
                                <td>{{ $a['hrs_prog'] ?? '' }}</td>
                                <td>{{ $a['servicio'] ?? '' }}</td>
                                <td>{{ $a['cuenta'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <a href="{{ route('horarios.index') }}" class="btn btn-secondary mt-3">Volver</a>
</div>
@endsection
