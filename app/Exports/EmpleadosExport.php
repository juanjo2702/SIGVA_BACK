<?php

namespace App\Exports;

use App\Models\Empleado;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmpleadosExport implements FromCollection, WithHeadings, WithMapping
{
    protected array $filtros;

    public function __construct(array $filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function collection()
    {
        $query = Empleado::activos();

        if (isset($this->filtros['solo_negativos']) && $this->filtros['solo_negativos']) {
            $query->where('saldo_vacaciones', '<', 0);
        }

        return $query->orderBy('apellido_paterno')->get();
    }

    public function headings(): array
    {
        return [
            '1° APELLIDO',
            '2° APELLIDO',
            'NOMBRE(S)',
            'C.I.',
            'CARGO',
            'FECHA DE INGRESO',
            'AÑOS DE SERVICIO',
            'DÍAS CORRESPONDIENTES',
            'SALDO DE DÍAS',
        ];
    }

    public function map($empleado): array
    {
        return [
            $empleado->apellido_paterno,
            $empleado->apellido_materno,
            $empleado->nombres,
            $empleado->ci,
            $empleado->cargo,
            $empleado->fecha_ingreso->format('d/m/Y'),
            $empleado->anos_servicio,
            $empleado->dias_correspondientes,
            $empleado->saldo_vacaciones,
        ];
    }
}
