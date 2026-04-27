<?php

namespace App\Exports;

use App\Models\Empleado;
use App\Models\Sede;
use App\Models\SolicitudVacacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteGeneralExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize, WithEvents, WithStrictNullComparison
{
    protected array $filtros;
    protected ?Sede $sede = null;

    public function __construct(array $filtros = [])
    {
        $this->filtros = $filtros;

        if (isset($this->filtros['sede_id']) && $this->filtros['sede_id'] !== 'todos' && $this->filtros['sede_id'] !== '') {
            $this->sede = Sede::find($this->filtros['sede_id']);
        }
    }

    public function collection()
    {
        $year = (int) ($this->filtros['ano'] ?? date('Y'));

        $query = Empleado::query()
            ->where('activo', true)
            ->with([
                'solicitudes' => function ($q) use ($year) {
                    $q->whereIn('estado', [
                        SolicitudVacacion::ESTADO_PENDIENTE,
                        SolicitudVacacion::ESTADO_PENDIENTE_DOCUMENTO,
                    ])->whereYear('fecha_solicitud', $year);
                }
            ])
            ->withSum([
                'solicitudes as dias_pendientes_aprobacion' => function ($q) use ($year) {
                    $q->whereIn('estado', [
                        SolicitudVacacion::ESTADO_PENDIENTE,
                        SolicitudVacacion::ESTADO_PENDIENTE_DOCUMENTO,
                    ])->whereYear('fecha_solicitud', $year);
                }
            ], 'dias_solicitados');

        if ($this->sede) {
            $query->where('sede_id', $this->sede->id);
        }

        $empleados = $query->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->get();

        $rows = collect();

        foreach ($empleados as $empleado) {
            $saldoActual = (float) $empleado->saldo_vacaciones;
            $diasPendientes = (float) ($empleado->dias_pendientes_aprobacion ?? 0);
            $reemplazantes = $empleado->solicitudes
                ->filter(fn ($solicitud) => !empty($solicitud->nombre_reemplazo))
                ->pluck('nombre_reemplazo')
                ->unique()
                ->values()
                ->implode(', ');

            $rows->push([
                $empleado->ci,
                $empleado->nombres,
                $empleado->apellido_paterno,
                $empleado->apellido_materno,
                $empleado->cargo,
                $empleado->fecha_ingreso ? $empleado->fecha_ingreso->format('d/m/Y') : '',
                $empleado->anos_servicio,
                $empleado->dias_correspondientes,
                $saldoActual,
                $diasPendientes,
                $saldoActual - $diasPendientes,
                $reemplazantes,
            ]);
        }

        return $rows;
    }

    public function title(): string
    {
        return $this->sede ? $this->sede->nombre : 'Consolidado de Vacaciones';
    }

    public function headings(): array
    {
        $year = $this->filtros['ano'] ?? date('Y');

        return [
            'C.I.',
            'NOMBRE(S)',
            '1er APELLIDO',
            '2do APELLIDO',
            'CARGO',
            'FECHA DE INGRESO',
            'ANOS DE ANTIGUEDAD',
            'DIAS CORRESPONDE GESTION ' . $year,
            'SALDO TOTAL (DIAS)',
            'DIAS PENDIENTES POR APROBAR',
            'SALDO PROYECTADO DESPUES DE PROGRAMAR',
            'REEMPLAZANTE PENDIENTE',
        ];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 2);

                $sheet->mergeCells('A1:L1');
                $nombreSede = strtoupper($this->sede ? $this->sede->nombre : 'TODAS LAS SEDES');
                $sheet->setCellValue('A1', $nombreSede);

                $sheet->mergeCells('A2:L2');
                $year = $this->filtros['ano'] ?? date('Y');
                $titulo = 'CONSOLIDADO GENERAL DE SALDOS DE VACACIONES - GESTION ' . $year;
                $sheet->setCellValue('A2', $titulo);

                $sheet->getStyle('A1:L1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(35);

                $sheet->getStyle('A2:L2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '333333']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C8E6C9']],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(25);

                $sheet->getRowDimension(3)->setRowHeight(35);
                $sheet->getStyle('A3:L3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                ]);

                $lastRow = $sheet->getHighestRow();
                if ($lastRow >= 4) {
                    $sheet->getStyle('A4:L' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $sheet->getStyle('A4:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F4:K' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle('I4:K' . $lastRow)->getNumberFormat()->setFormatCode('0.0');

                    for ($i = 4; $i <= $lastRow; $i++) {
                        $saldoActual = $sheet->getCell('I' . $i)->getValue();
                        $saldoProyectado = $sheet->getCell('K' . $i)->getValue();

                        if ($saldoActual < 0) {
                            $sheet->getStyle('I' . $i)->getFont()->getColor()->setRGB('C62828');
                            $sheet->getStyle('I' . $i)->getFont()->setBold(true);
                        }

                        if ($saldoProyectado < 0) {
                            $sheet->getStyle('K' . $i)->getFont()->getColor()->setRGB('C62828');
                            $sheet->getStyle('K' . $i)->getFont()->setBold(true);
                        }
                    }
                }

                $sheet->freezePane('A4');
            },
        ];
    }
}
