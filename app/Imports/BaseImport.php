<?php

namespace App\Imports;

use App\Models\Empleado;
use App\Models\Horario;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BaseImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'BASE' => new BaseHojaImport(),
        ];
    }
}

class BaseHojaImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $coordinador = $row['team_leader'] ?? null;
            $asesor = $row['asesor_a'] ?? null;

            if (!$asesor) continue;

            // Buscar o crear empleado
            $empleado = Empleado::updateOrCreate(
                ['nombre' => $asesor],
                ['coordinador' => $coordinador]
            );

            // Crear o actualizar horario
            Horario::updateOrCreate(
                [
                    'empleado_id' => $empleado->id,
                    'fecha' => $row['fecha_dia'] ?? null,
                ],
                [
                    'hi' => $row['hi'] ?? null,
                    'hf' => $row['hf'] ?? null,
                    'hid1' => $row['hid1'] ?? null,
                    'hfd1' => $row['hfd1'] ?? null,
                    'hia' => $row['hia'] ?? null,
                    'hfa' => $row['hfa'] ?? null,
                    'hrs' => $row['hrs'] ?? null,
                    'prog' => $row['prog'] ?? null,
                ]
            );
        }
    }
}
