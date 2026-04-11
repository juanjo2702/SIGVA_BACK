<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlantillaEmpleadosExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    /**
     * Datos de ejemplo para la plantilla
     */
    public function array(): array
    {
        return [
            [
                'HUACANI',
                'YANAPA',
                'TEOFILO',
                '1234567',
                'Masculino',
                'Completo',
                'LA PAZ',
                'AUXILIAR ADMINISTRATIVO',
                '2020-01-15',
                15.5
            ],
            [
                'PEREZ',
                'MAMANI',
                'MARIA',
                '7654321',
                'Femenino',
                'Medio Tiempo',
                'COCHABAMBA',
                'SECRETARIA',
                '2019-06-01',
                20.0
            ],
        ];
    }

    /**
     * Encabezados de la plantilla
     */
    public function headings(): array
    {
        return [
            'Apellido Paterno',
            'Apellido Materno',
            'Nombres',
            'CI',
            'Genero',
            'Tipo Contrato',
            'Sede',
            'Cargo',
            'Fecha de Ingreso',
            'Saldo de Dias'
        ];
    }

    /**
     * Estilos para la plantilla
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para encabezados
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4A5568'],
                ],
            ],
        ];
    }

    /**
     * Anchos de columna
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20, // Apellido Paterno
            'B' => 20, // Apellido Materno
            'C' => 20, // Nombres
            'D' => 15, // CI
            'E' => 15, // Género
            'F' => 18, // Tipo Contrato
            'G' => 28, // Sede
            'H' => 30, // Cargo
            'I' => 18, // Fecha de Ingreso
            'J' => 15, // Saldo de Días
        ];
    }
}
