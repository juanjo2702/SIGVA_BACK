<?php

namespace App\Exports;

use App\Models\Empleado;
use App\Models\Sede;
use App\Models\SolicitudVacacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReporteGeneralExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize, WithEvents
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

    /**
     * Construimos la colección de filas.
     * Mostramos un resumen consolidado por empleado.
     */
    public function collection()
    {
        $query = Empleado::query()->where('activo', true);

        if ($this->sede) {
            $query->where('sede_id', $this->sede->id);
        }

        $empleados = $query->orderBy('apellido_paterno')
                           ->orderBy('apellido_materno')
                           ->get();

        $rows = collect();

        foreach ($empleados as $empleado) {
            $rows->push([
                $empleado->ci,
                $empleado->nombres,
                $empleado->apellido_paterno,
                $empleado->apellido_materno,
                $empleado->cargo,
                $empleado->fecha_ingreso ? $empleado->fecha_ingreso->format('d/m/Y') : '',
                $empleado->anos_servicio,
                $empleado->dias_correspondientes,
                (float) $empleado->saldo_vacaciones
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
            '1º APELLIDO',
            '2º APELLIDO',
            'CARGO',
            'FECHA DE INGRESO',
            'AÑOS DE ANTIGÜEDAD',
            'DÍAS CORRESPONDE GESTIÓN ' . $year,
            'SALDO TOTAL (DÍAS)'
        ];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [ // Fila de cabecera (en la fila 3 realmente por el AfterSheet)
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Insertamos 2 filas al inicio para el cabezal institucional
                $sheet->insertNewRowBefore(1, 2);

                // FILA 1: Nombre de la Sede
                $sheet->mergeCells('A1:I1');
                $nombreSede = strtoupper($this->sede ? $this->sede->nombre : 'TODAS LAS SEDES');
                $sheet->setCellValue('A1', $nombreSede);

                // FILA 2: Título del Reporte
                $sheet->mergeCells('A2:I2');
                $year = $this->filtros['ano'] ?? date('Y');
                $titulo = "CONSOLIDADO GENERAL DE SALDOS DE VACACIONES - GESTIÓN " . $year;
                $sheet->setCellValue('A2', $titulo);

                // Estilo Sede (Fila 1)
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']]
                ]);
                $sheet->getRowDimension(1)->setRowHeight(35);

                // Estilo Título (Fila 2)
                $sheet->getStyle('A2:I2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '333333']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C8E6C9']]
                ]);
                $sheet->getRowDimension(2)->setRowHeight(25);

                // Estilo Cabeceras de Columnas (Ahora en Fila 3)
                $sheet->getRowDimension(3)->setRowHeight(35);
                $sheet->getStyle('A3:I3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]
                ]);

                // Estilos de datos (Fila 4 en adelante)
                $lastRow = $sheet->getHighestRow();
                if ($lastRow >= 4) {
                    $sheet->getStyle('A4:I' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    // Alineación
                    $sheet->getStyle('A4:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // CI
                    $sheet->getStyle('F4:I' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Fechas y Números

                    // Formato para el saldo (columna I)
                    $sheet->getStyle('I4:I' . $lastRow)->getNumberFormat()->setFormatCode('0.0');

                    // Resaltar saldos negativos en rojo
                    for ($i = 4; $i <= $lastRow; $i++) {
                        $val = $sheet->getCell('I' . $i)->getValue();
                        if ($val < 0) {
                            $sheet->getStyle('I' . $i)->getFont()->getColor()->setRGB('C62828');
                            $sheet->getStyle('I' . $i)->getFont()->setBold(true);
                        }
                    }
                }

                $sheet->freezePane('A4');
            },
        ];
    }
}

