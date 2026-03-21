<?php

namespace App\Exports;

use App\Models\SolicitudVacacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class SolicitudesExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected array $filtros;
    protected ?\App\Models\Sede $sede = null;

    public function __construct(array $filtros = [])
    {
        $this->filtros = $filtros;
        if (isset($this->filtros['sede_id']) && $this->filtros['sede_id'] !== 'todos' && $this->filtros['sede_id'] !== '') {
            $this->sede = \App\Models\Sede::find($this->filtros['sede_id']);
        }
    }

    public function collection()
    {
        $query = SolicitudVacacion::with('empleado');

        if (isset($this->filtros['ano'])) {
            $query->whereYear('fecha_solicitud', $this->filtros['ano']);
        }

        if (isset($this->filtros['estado']) && $this->filtros['estado'] !== 'todos') {
            $query->where('estado', $this->filtros['estado']);
        }

        if ($this->sede) {
            $query->whereHas('empleado', function($q) {
                $q->where('sede_id', $this->sede->id);
            });
        }

        return $query->orderBy('fecha_solicitud', 'desc')->get();
    }

    public function title(): string
    {
        return $this->sede ? substr($this->sede->nombre, 0, 31) : 'Solicitudes';
    }

    public function headings(): array
    {
        return [
            'ID',
            'EMPLEADO',
            'C.I.',
            'FECHA SOLICITUD',
            'FECHA INICIO',
            'FECHA FIN',
            'TIPO',
            'DÍAS SOLICITADOS',
            'ESTADO',
            'MOTIVO RECHAZO',
        ];
    }

    public function map($solicitud): array
    {
        return [
            $solicitud->id,
            $solicitud->empleado->nombre_completo,
            $solicitud->empleado->ci,
            $solicitud->fecha_solicitud->format('d/m/Y'),
            $solicitud->fecha_inicio->format('d/m/Y'),
            $solicitud->fecha_fin->format('d/m/Y'),
            $this->traducirTipo($solicitud->tipo),
            $solicitud->dias_solicitados,
            $this->traducirEstado($solicitud->estado),
            $solicitud->motivo_rechazo,
        ];
    }

    private function traducirTipo(string $tipo): string
    {
        return match ($tipo) {
            'completo' => 'Día Completo',
            'parcial_manana' => 'Parcial Mañana',
            'parcial_tarde' => 'Parcial Tarde',
            default => $tipo,
        };
    }

    private function traducirEstado(string $estado): string
    {
        return match ($estado) {
            'pendiente' => 'Pendiente',
            'aprobada' => 'Aprobada',
            'rechazada' => 'Rechazada',
            default => $estado,
        };
    }
}
