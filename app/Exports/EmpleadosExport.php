<?php

namespace App\Exports;

use App\Models\Empleado;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmpleadosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents, WithTitle
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
        $query = Empleado::activos();

        if (isset($this->filtros['solo_negativos']) && $this->filtros['solo_negativos']) {
            $query->where('saldo_vacaciones', '<', 0);
        }

        if ($this->sede) {
            $query->where('sede_id', $this->sede->id);
        }

        return $query->orderBy('apellido_paterno')->get();
    }

    public function title(): string
    {
        return $this->sede ? substr($this->sede->nombre, 0, 31) : 'Empleados';
    }

    public function headings(): array
    {
        return [
            'C.I.',
            'NOMBRE(S)',
            '1° APELLIDO',
            '2° APELLIDO',
            'CARGO',
            'FECHA DE INGRESO',
            'AÑOS DE ANTIGUEDAD',
            'DÍAS CORRESPONDIENTES (AÑO ACTUAL)',
            'SALDO TOTAL DE VACACIONES',
        ];
    }

    public function map($empleado): array
    {
        return [
            $empleado->ci,
            $empleado->nombres,
            $empleado->apellido_paterno,
            $empleado->apellido_materno,
            $empleado->cargo,
            $empleado->fecha_ingreso ? $empleado->fecha_ingreso->format('d/m/Y') : '',
            $empleado->anos_servicio,
            $empleado->dias_correspondientes,
            (float) $empleado->saldo_vacaciones,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1565C0'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastColumn = $sheet->getHighestColumn();

                // Altura de cabecera
                $sheet->getRowDimension(1)->setRowHeight(30);

                // Bordes y alineación para los datos
                $range = "A1:{$lastColumn}{$lastRow}";
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Centrar CI, Fechas, Años y Números
                $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F2:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Formato para el saldo (columna I)
                $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('0.0');

                // Color para saldos negativos
                for ($row = 2; $row <= $lastRow; $row++) {
                    $val = $sheet->getCell("I{$row}")->getValue();
                    if ($val < 0) {
                        $sheet->getStyle("I{$row}")->getFont()->getColor()->setRGB('C62828');
                        $sheet->getStyle("I{$row}")->getFont()->setBold(true);
                    }
                }
            },
        ];
    }
}
