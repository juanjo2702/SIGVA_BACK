<?php

namespace App\Exports;

use App\Models\Empleado;
use App\Models\Sede;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReporteGeneralMultipleExport implements WithMultipleSheets
{
    use Exportable;

    protected array $filtros;

    public function __construct(array $filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function sheets(): array
    {
        $sheets = [];

        $sedesIds = Empleado::where('activo', true)
            ->pluck('sede_id')
            ->filter()
            ->unique()
            ->values();

        $sedes = Sede::query()
            ->whereIn('id_sede', $sedesIds)
            ->orderBy('nombre')
            ->get();

        foreach ($sedes as $sede) {
            $filtrosSede = $this->filtros;
            $filtrosSede['sede_id'] = $sede->id;
            $sheets[] = new ReporteGeneralExport($filtrosSede);
        }

        if (empty($sheets)) {
            $sheets[] = new ReporteGeneralExport($this->filtros);
        }

        return $sheets;
    }
}
