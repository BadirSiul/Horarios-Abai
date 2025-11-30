<?php

namespace App\Imports;

use App\Models\Horario;
use App\Models\Empleado;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class HorariosImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'BASE' => new HorariosHojaImport(),
        ];
    }
}

class HorariosHojaImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $contador = 0;
        $errores = [];
        
        // Debug solo para las primeras 3 filas (reducir logging)
        $debugCount = 0;
        
        foreach ($rows as $index => $row) {
            // Convertir Collection a array para facilitar el acceso
            $rowArray = $row->toArray();
            
            // Normalizar los nombres de columnas (Laravel Excel convierte a minúsculas y reemplaza espacios con _)
            // Intentar diferentes variaciones de nombres de columnas
            // Nota: Laravel Excel puede convertir "ASESOR (A)" a "asesor_(a)" o "asesor (a)"
            $asesor = $this->getValue($rowArray, ['asesor_(a)', 'asesor (a)', 'asesor_a', 'asesor a', 'asesor']);
            $teamLeader = $this->getValue($rowArray, ['team_leader', 'team leader', 'teamleader', 'team-leader']);
            $fecha = $this->getValue($rowArray, ['fecha']);
            $dia = $this->getValue($rowArray, ['día', 'dia', 'd']);
            $hi = $this->getValue($rowArray, ['hi', 'hora_inicio', 'hora inicio', 'hora-inicio']);
            $hf = $this->getValue($rowArray, ['hf', 'hora_fin', 'hora fin', 'hora-fin']);
            $hid1 = $this->getValue($rowArray, ['hid1', 'hora_inicio_descanso_1', 'hora inicio descanso 1', 'hora-inicio-descanso-1']);
            $hfd1 = $this->getValue($rowArray, ['hfd1', 'hora_fin_descanso_1', 'hora fin descanso 1', 'hora-fin-descanso-1']);
            $hia = $this->getValue($rowArray, ['hia', 'hora_inicio_almuerzo', 'hora inicio almuerzo', 'hora-inicio-almuerzo']);
            $hfa = $this->getValue($rowArray, ['hfa', 'hora_fin_almuerzo', 'hora fin almuerzo', 'hora-fin-almuerzo']);
            $hrsProg = $this->getValue($rowArray, ['hrs_prog', 'hrs prog', 'horas_prog', 'horas prog', 'horas_programadas', 'horas-programadas']);

            // Validar que exista el asesor
            if (empty($asesor) || trim($asesor) === '') {
                continue;
        }

            $nombreAsesor = trim($asesor);
            $teamLeader = trim($teamLeader ?? '');

            // Buscar o crear el empleado (asesor) y actualizar el coordinador
            $empleado = Empleado::updateOrCreate(
                ['nombre' => $nombreAsesor],
                ['coordinador' => $teamLeader]
        );

            // Si el empleado ya existía pero no tenía coordinador, actualizarlo
            if (empty($empleado->coordinador) && !empty($teamLeader)) {
                $empleado->coordinador = $teamLeader;
                $empleado->save();
            }

            // Convertir fecha - IMPORTANTE: verificar que se lea correctamente
            $fechaConvertida = $this->excelDate($fecha);
            
            // Debug solo para las primeras 3 filas (reducir logging)
            if ($debugCount < 3) {
                \Log::info("Fila " . ($index + 2) . " - Asesor: $nombreAsesor | Fecha original: " . ($fecha ?? 'NULL') . " | Fecha convertida: " . ($fechaConvertida ?? 'NULL'));
                $debugCount++;
            }
            
            // Si no hay fecha válida, saltar esta fila PERO registrar el error
            if (empty($fechaConvertida)) {
                $errores[] = "Fila " . ($index + 2) . " - Fecha inválida o vacía. Asesor: $nombreAsesor, Fecha original: " . ($fecha ?? 'NULL');
                continue;
            }
            
            // Convertir día - SIEMPRE usar la fecha para calcular el día, ignorar el valor del campo "dia" si es numérico
            $diaConvertido = $this->excelDay($dia, $fechaConvertida, true);

            // Usar updateOrCreate para evitar duplicados
            // IMPORTANTE: La clave única es empleado_id + fecha, así que cada fecha diferente crea un registro nuevo
            try {
                Horario::updateOrCreate(
                    [
                        'empleado_id' => $empleado->id,
                        'fecha' => $fechaConvertida,
                    ],
                    [
                        'dia' => $diaConvertido,
                        'hi' => $this->excelTime($hi),
                        'hf' => $this->excelTime($hf),
                        'hid1' => $this->excelTime($hid1),
                        'hfd1' => $this->excelTime($hfd1),
                        'hia' => $this->excelTime($hia),
                        'hfa' => $this->excelTime($hfa),
                        'hrs_prog' => $this->cleanHours($hrsProg),
                    ]
                );
                $contador++;
            } catch (\Exception $e) {
                $errores[] = "Fila " . ($index + 2) . " (Asesor: $nombreAsesor, Fecha: $fechaConvertida): " . $e->getMessage();
            }
        }
        
        // Log de información
        \Log::info("Importación completada. Registros procesados: $contador");
        if (count($errores) > 0) {
            \Log::warning('Errores en importación de horarios', ['errores' => $errores]);
        }
    }

    /**
     * Obtiene un valor del array intentando diferentes claves
     * También busca variaciones con diferentes normalizaciones
     */
    private function getValue($row, array $keys)
    {
        // Primero intentar con las claves exactas
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        
        // Si no se encuentra, buscar en todas las claves del array (case-insensitive)
        foreach ($row as $rowKey => $rowValue) {
            $rowKeyLower = mb_strtolower(trim($rowKey));
            foreach ($keys as $key) {
                $keyLower = mb_strtolower(trim($key));
                // Comparar normalizando espacios, guiones y caracteres especiales
                $rowKeyNormalized = preg_replace('/[_\s\-\(\)]/', '', $rowKeyLower);
                $keyNormalized = preg_replace('/[_\s\-\(\)]/', '', $keyLower);
                if ($rowKeyNormalized === $keyNormalized && $rowValue !== null && $rowValue !== '') {
                    return $rowValue;
    }
            }
        }
        
        return null;
    }

    /**
     * Convierte una fecha de Excel a formato Y-m-d
     * Maneja múltiples formatos: DD/MM/YYYY, "10 de noviembre de 2025", fechas seriales de Excel, etc.
     * Optimizado para mejor rendimiento
     */
    private function excelDate($value)
    {
        if (empty($value)) {
            return null;
        }

        // Si es numérico, es una fecha serial de Excel
        // Optimización: validar rango razonable antes de convertir
        if (is_numeric($value)) {
            // Excel fecha serial válida está entre 1 (1900-01-01) y ~50000 (2037+)
            // Si está fuera de rango, probablemente no es una fecha
            if ($value < 1 || $value > 100000) {
                return null;
            }
            
            try {
                // Usar el método de PhpSpreadsheet pero con validación previa
                // Esto es más confiable que cálculo manual debido al bug de Excel 1900
                $dateTime = Date::excelToDateTimeObject($value);
                if ($dateTime) {
                    return $dateTime->format('Y-m-d');
        }
                return null;
            } catch (\Exception $e) {
                // Si falla la conversión, retornar null en lugar de lanzar excepción
                return null;
    }
        }

        // Si es string, intentar parsearlo
        if (is_string($value)) {
            $value = trim($value);
            
            // Formato español completo: "10 de noviembre de 2025" o "10 de Noviembre de 2025"
            if (preg_match('/^(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})$/i', $value, $matches)) {
                try {
                    $day = (int)$matches[1];
                    $monthName = mb_strtolower($matches[2]);
                    $year = (int)$matches[3];
                    
                    // Mapeo de meses en español
                    $meses = [
                        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
                        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
                        'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
                    ];
                    
                    if (isset($meses[$monthName])) {
                        $month = $meses[$monthName];
                        $date = Carbon::create($year, $month, $day);
                        return $date->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // Continuar con otros métodos
                }
            }
            
            // Formato DD/MM/YYYY (formato común en español)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
                try {
                    $day = (int)$matches[1];
                    $month = (int)$matches[2];
                    $year = (int)$matches[3];
                    $date = Carbon::create($year, $month, $day);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Continuar con otros métodos
                }
            }
            
            // Intentar parsear con Carbon (acepta muchos formatos)
            try {
                // Configurar locale español para Carbon
                Carbon::setLocale('es');
                $date = Carbon::parse($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Log para depuración
                \Log::warning("No se pudo parsear la fecha: $value", ['error' => $e->getMessage()]);
                return null;
            }
        }

        return null;
    }

    /**
     * Convierte un valor de tiempo de Excel a formato HH:MM
     * Excel almacena tiempos como decimales (0.5 = 12:00, 0.25 = 6:00)
     */
    private function excelTime($value)
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0' || $value === '0:00') {
            return null;
        }

        // Si es string "DESCANSO" o similar, retornar null
        if (is_string($value)) {
            $value = trim($value);
            if (strtoupper($value) === 'DESCANSO' || strtoupper($value) === 'REST' || $value === '') {
                return null;
            }
        }

        // Si ya es un string con formato de tiempo (H:MM, HH:MM, HH:MM:SS)
        if (is_string($value)) {
            $value = trim($value);
            // Patrón más flexible: acepta H:MM o HH:MM
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                // Validar que las horas y minutos sean válidos
                if ($hours >= 0 && $hours < 24 && $minutes >= 0 && $minutes < 60) {
                    return sprintf('%02d:%02d', $hours, $minutes);
                }
            }
        }

        // Si es numérico, es un tiempo decimal de Excel
        if (is_numeric($value)) {
            try {
                // Excel almacena tiempos como fracción del día (0.5 = mediodía)
                // Si el valor es muy pequeño o muy grande, puede ser un error
                if ($value < 0 || $value >= 1) {
                    return null;
                }
                
                $totalSeconds = round($value * 86400); // 86400 segundos en un día
                $hours = floor($totalSeconds / 3600);
                $minutes = floor(($totalSeconds % 3600) / 60);
                
                // Validar que las horas y minutos sean válidos
                if ($hours >= 0 && $hours < 24 && $minutes >= 0 && $minutes < 60) {
                    return sprintf('%02d:%02d', $hours, $minutes);
                }
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Convierte el campo día (puede ser fecha de Excel o nombre de día)
     * @param mixed $value Valor del campo día del Excel
     * @param string|null $fecha Fecha ya convertida (Y-m-d)
     * @param bool $priorizarFecha Si es true, siempre usa la fecha para calcular el día
     */
    private function excelDay($value, $fecha = null, $priorizarFecha = false)
    {
        // Mapeo de días en español (minúsculas) a formato capitalizado
        $diasEspanolLower = [
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miércoles' => 'Miércoles',
            'miercoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sábado' => 'Sábado',
            'sabado' => 'Sábado',
            'domingo' => 'Domingo',
        ];

        // SIEMPRE priorizar la fecha si está disponible (para evitar números como 45977)
        if ($fecha) {
            try {
                $date = Carbon::parse($fecha);
                $dayName = $date->locale('es')->dayName;
                // Capitalizar primera letra
                $diaCalculado = ucfirst(mb_strtolower($dayName));
                
                // Si priorizarFecha es true, devolver directamente el día calculado
                if ($priorizarFecha) {
                    return $diaCalculado;
                }
                
                // Si el valor es numérico grande (fecha serial de Excel), ignorarlo y usar la fecha
                if (is_numeric($value) && $value > 1000) {
                    return $diaCalculado;
                }
                
                // Si el valor es un string válido de día, verificar que coincida con el calculado
                if (!empty($value) && is_string($value)) {
                    $valueLower = mb_strtolower(trim($value));
                    if (isset($diasEspanolLower[$valueLower])) {
                        // Verificar que coincida con el día calculado
                        if (mb_strtolower($diasEspanolLower[$valueLower]) === mb_strtolower($diaCalculado)) {
                            return $diasEspanolLower[$valueLower];
                        }
                    }
                }
                
                // Devolver el día calculado de la fecha
                return $diaCalculado;
            } catch (\Exception $e) {
                // Si falla, continuar con el procesamiento normal
            }
        }

        // Si no hay fecha, intentar procesar el valor directamente
        if (empty($value)) {
            return null;
        }

        // Si es numérico pequeño (1-7), podría ser un día de la semana
        if (is_numeric($value) && $value >= 1 && $value <= 7) {
            $diasNumericos = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 
                              5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
            return $diasNumericos[(int)$value] ?? null;
        }

        // Si es string, normalizarlo
        $valueStr = trim((string)$value);
        $valueLower = mb_strtolower($valueStr);

        // Si ya está en español (minúsculas), capitalizarlo
        if (isset($diasEspanolLower[$valueLower])) {
            return $diasEspanolLower[$valueLower];
        }

        // Si no coincide con ningún patrón, devolver null (se calculará desde la fecha en la vista)
        return null;
    }

    /**
     * Limpia y convierte horas programadas
     * Puede venir como "5:45" (string) o como decimal (0.23958333333333)
     * SIEMPRE devuelve formato HH:MM o null
     */
    private function cleanHours($value)
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0' || $value === '0:00') {
            return null;
        }

        // Si es numérico, puede ser una fracción de día o un número de horas
        if (is_numeric($value)) {
            // Si es un decimal pequeño (< 1), es una fracción de día (formato Excel)
            if ($value > 0 && $value < 1) {
                $totalMinutes = round($value * 1440); // 1440 minutos en un día
                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                return sprintf('%d:%02d', $hours, $minutes);
            }
            // Si es un número entero pequeño (probablemente horas), convertir a formato HH:MM
            if ($value >= 1 && $value < 24) {
                return sprintf('%d:00', (int)$value);
            }
            // Si es un número grande, podría ser minutos totales
            if ($value >= 60 && $value < 1440) {
                $hours = floor($value / 60);
                $minutes = $value % 60;
                return sprintf('%d:%02d', $hours, $minutes);
            }
            // Para otros casos, intentar tratarlo como fracción de día de todas formas
            if ($value > 0) {
                $totalMinutes = round($value * 1440);
                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                // Validar que sea razonable (menos de 24 horas)
                if ($hours < 24) {
                    return sprintf('%d:%02d', $hours, $minutes);
                }
            }
            return null;
        }

        // Si es string con formato de tiempo (H:MM o HH:MM)
        if (is_string($value)) {
            $value = trim($value);
            // Verificar si ya está en formato correcto
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                // Validar
                if ($hours >= 0 && $hours < 24 && $minutes >= 0 && $minutes < 60) {
        return sprintf('%02d:%02d', $hours, $minutes);
                }
            }
            // Intentar extraer número de string
            $cleaned = preg_replace('/[^0-9.]/', '', $value);
            if ($cleaned !== '') {
                $numValue = (float)$cleaned;
                // Si es un decimal pequeño, convertir
                if ($numValue > 0 && $numValue < 1) {
                    $totalMinutes = round($numValue * 1440);
                    $hours = floor($totalMinutes / 60);
                    $minutes = $totalMinutes % 60;
                    return sprintf('%d:%02d', $hours, $minutes);
                }
            }
        }

        return null;
    }
}
