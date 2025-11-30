<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\HorariosImport;
use App\Models\Horario;
use App\Models\Empleado;
use Maatwebsite\Excel\Facades\Excel;

class HorarioController extends Controller
{
    public function index(Request $request)
    {
        // Obtener todos los Team Leaders únicos para el filtro
        $teamLeaders = Empleado::whereNotNull('coordinador')
            ->where('coordinador', '!=', '')
            ->distinct()
            ->orderBy('coordinador')
            ->pluck('coordinador')
            ->unique()
            ->values();

        // Construir la consulta base
        $query = Horario::with('empleado');

        // Aplicar filtro por Team Leader si se proporciona
        if ($request->filled('team_leader')) {
            $query->whereHas('empleado', function ($q) use ($request) {
                $q->where('coordinador', $request->team_leader);
            });
        }

        // Clonar la query para la agrupación (sin paginación)
        $queryAgrupada = clone $query;

        // Agrupar horarios por empleado y mostrar solo la semana más reciente
        $horariosAgrupados = $queryAgrupada->orderBy('fecha', 'desc')
            ->orderBy('empleado_id')
            ->get()
            ->groupBy('empleado_id')
            ->map(function ($horariosEmpleado) {
                // Obtener la fecha más reciente de este empleado
                $fechaMasReciente = $horariosEmpleado->pluck('fecha')->sort()->last();
                
                if (!$fechaMasReciente) {
                    return null;
                }
                
                // Calcular el rango de la semana de la fecha más reciente
                $fechaMasRecienteCarbon = \Carbon\Carbon::parse($fechaMasReciente);
                $fechaInicioSemana = $fechaMasRecienteCarbon->copy()->startOfWeek();
                $fechaFinSemana = $fechaMasRecienteCarbon->copy()->endOfWeek();
                
                // Filtrar solo los horarios de esta semana
                $horariosSemana = $horariosEmpleado->filter(function ($horario) use ($fechaInicioSemana, $fechaFinSemana) {
                    $fechaHorario = \Carbon\Carbon::parse($horario->fecha);
                    return $fechaHorario->between($fechaInicioSemana, $fechaFinSemana, true); // true = inclusive
                });
                
                // Si no hay horarios de esta semana, usar todos (fallback)
                $horariosParaCalcular = $horariosSemana->count() > 0 ? $horariosSemana : $horariosEmpleado;
                
                $fechasSemana = $horariosParaCalcular->pluck('fecha')->sort()->values();
                $fechaInicio = $fechasSemana->first();
                $fechaFin = $fechasSemana->last();
                
                // Calcular el horario más común (moda) para cada campo usando solo la semana
                $calcularModa = function($campo) use ($horariosParaCalcular) {
                    $valores = $horariosParaCalcular->pluck($campo)->filter(function($v) {
                        return $v !== null && $v !== '' && $v !== '00:00';
                    });
                    
                    if ($valores->isEmpty()) {
                        return null;
                    }
                    
                    // Contar frecuencia de cada valor
                    $frecuencias = $valores->countBy();
                    // Obtener el valor más frecuente
                    $moda = $frecuencias->sortDesc()->keys()->first();
                    
                    return $moda;
                };
                
                // Obtener horarios más comunes
                $horarioComun = [
                    'inicio_turno' => $calcularModa('hi'),
                    'fin_turno' => $calcularModa('hf'),
                    'inicio_descanso' => $calcularModa('hid1'),
                    'fin_descanso' => $calcularModa('hfd1'),
                    'inicio_almuerzo' => $calcularModa('hia'),
                    'fin_almuerzo' => $calcularModa('hfa'),
                    'horas_prog' => $calcularModa('hrs_prog'),
                ];
                
                // Identificar días de descanso solo de la semana actual
                $diasDescanso = $horariosParaCalcular->filter(function ($horario) {
                    // Un día es de descanso si no tiene inicio de turno o está en 00:00
                    $sinHorario = empty($horario->hi) || $horario->hi === '00:00' || $horario->hi === null;
                    // También verificar si todos los horarios están vacíos
                    $todosVacios = empty($horario->hi) && empty($horario->hf) && 
                                   empty($horario->hid1) && empty($horario->hfd1) && 
                                   empty($horario->hia) && empty($horario->hfa);
                    return $sinHorario || $todosVacios;
                })->map(function ($horario) {
                    // Obtener el nombre del día en español
                    if ($horario->fecha) {
                        return ucfirst(\Carbon\Carbon::parse($horario->fecha)->locale('es')->dayName);
                    }
                    return null;
                })->filter()->unique()->values();
                
                return [
                    'empleado' => $horariosEmpleado->first()->empleado,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'total_dias' => $fechasSemana->count(),
                    'dias_descanso' => $diasDescanso,
                    'horario_comun' => $horarioComun,
                    'horarios' => $horariosParaCalcular->sortBy('fecha')->values()
                ];
            })
            ->filter() // Eliminar grupos nulos
            ->sortByDesc(function($grupo) {
                // Ordenar por fecha más reciente
                return $grupo['fecha_fin'];
            })
            ->values();

        // Ordenar y paginar para la tabla detallada
        $horarios = $query->orderBy('fecha', 'asc')
            ->orderBy('empleado_id')
            ->orderBy('fecha')
            ->paginate(50)
            ->withQueryString(); // Mantener parámetros de búsqueda en la paginación

        return view('horarios.index', compact('horarios', 'teamLeaders', 'horariosAgrupados'));
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx,xls,csv',
            'limpiar_anteriores' => 'sometimes|boolean'
        ]);

        try {
            // Aumentar el tiempo de ejecución para archivos grandes
            set_time_limit(300); // 5 minutos
            ini_set('memory_limit', '512M');
            
            // Si se marca la opción de limpiar, eliminar todos los horarios antes de importar
            if ($request->has('limpiar_anteriores') && $request->limpiar_anteriores) {
                Horario::truncate(); // Elimina todos los registros de horarios
                \Log::info('Datos anteriores eliminados antes de importar nuevo archivo');
            }
            
            Excel::import(new HorariosImport, $request->file('archivo'));
            
            $mensaje = $request->has('limpiar_anteriores') && $request->limpiar_anteriores
                ? 'Archivo importado correctamente. Datos anteriores eliminados. ✅'
                : 'Archivo importado correctamente ✅';
            
            return redirect()->route('horarios.index')->with('success', $mensaje);
        } catch (\Exception $e) {
            return redirect()->route('horarios.index')
                ->with('error', 'Error al importar el archivo: ' . $e->getMessage());
        }
    }

    public function limpiar()
    {
        try {
            Horario::truncate();
            return redirect()->route('horarios.index')
                ->with('success', 'Todos los horarios han sido eliminados ✅');
        } catch (\Exception $e) {
            return redirect()->route('horarios.index')
                ->with('error', 'Error al limpiar datos: ' . $e->getMessage());
        }
    }
}
