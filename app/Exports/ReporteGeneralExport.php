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
     * Incluimos a todos los empleados activos de la sede y sus solicitudes del año.
     */
    public function collection()
    {
        $year = $this->filtros['ano'] ?? date('Y');
        $modo = $this->filtros['modo'] ?? 'gestion';

        // Convertimos fechas a Carbon para asegurar precisión en la consulta
        $fechaDesde = !empty($this->filtros['fecha_desde']) ? \Carbon\Carbon::parse($this->filtros['fecha_desde'])->startOfDay() : null;
        $fechaHasta = !empty($this->filtros['fecha_hasta']) ? \Carbon\Carbon::parse($this->filtros['fecha_hasta'])->endOfDay() : null;

        $query = Empleado::query()->where('activo', true);

        if ($this->sede) {
            $query->where('sede_id', $this->sede->id);
        }

        if ($modo === 'rango' && $fechaDesde && $fechaHasta) {
            // Filtro 1: Solo empleados que tengan al menos una vacación que INICIE en ese rango
            $query->whereHas('solicitudes', function($q) use ($fechaDesde, $fechaHasta) {
                $q->whereIn('estado', ['aprobada', 'pendiente', 'pendiente_documento'])
                  ->where('fecha_inicio', '>=', $fechaDesde)
                  ->where('fecha_inicio', '<=', $fechaHasta);
            });

            // Filtro 2: Cargar SOLO esas solicitudes específicas (para no traer las de todo el año)
            $query->with(['solicitudes' => function($q) use ($fechaDesde, $fechaHasta) {
                $q->whereIn('estado', ['aprobada', 'pendiente', 'pendiente_documento'])
                  ->where('fecha_inicio', '>=', $fechaDesde)
                  ->where('fecha_inicio', '<=', $fechaHasta)
                  ->orderBy('fecha_inicio', 'asc');
            }]);
        } else {
            // Plan Anual: Cargamos solicitudes del año para todos los empleados
            $query->with(['solicitudes' => function($q) use ($year) {
                $q->whereIn('estado', ['aprobada', 'pendiente', 'pendiente_documento'])
                  ->whereYear('fecha_inicio', $year)
                  ->orderBy('fecha_inicio', 'asc');
            }]);
        }

        $empleados = $query->orderBy('apellido_paterno')->orderBy('apellido_materno')->get();

        $rows = collect();

        foreach ($empleados as $empleado) {
            if ($empleado->solicitudes->isEmpty()) {
                // En modo gestión mostramos a todos aunque no tengan fechas
                if ($modo !== 'rango') {
                    $rows->push($this->formatRowData($empleado, null));
                }
            } else {
                foreach ($empleado->solicitudes as $solicitud) {
                    $rows->push($this->formatRowData($empleado, $solicitud));
                }
            }
        }

        return $rows;
    }

    /**
     * Helper para dar formato a los datos de la fila
     */
    private function formatRowData($empleado, $solicitud): array
    {
        $cargoReemplazo = '';
        if ($solicitud && $solicitud->nombre_reemplazo) {
            $reemplazoMatch = Empleado::where('nombres', 'like', '%' . $solicitud->nombre_reemplazo . '%')
                ->orWhere('apellido_paterno', 'like', '%' . $solicitud->nombre_reemplazo . '%')
                ->first();
            if ($reemplazoMatch) {
                $cargoReemplazo = $reemplazoMatch->cargo;
            }
        }

        $saldoActual = (float) $empleado->saldo_vacaciones;
        $diasTomados = $solicitud ? (float) $solicitud->dias_solicitados : 0;

        // Si la solicitud ya está aprobada, el saldo_vacaciones actual ya tiene el descuento.
        // Queremos mostrar cuánto tenía ANTES de ese descuento específico.
        // Nota: Si hay múltiples aprobadas, este cálculo es solo aproximado por fila.
        $saldoAntes = ($solicitud && $solicitud->estado === 'aprobada') ? ($saldoActual + $diasTomados) : $saldoActual;
        $saldoDespues = ($solicitud && $solicitud->estado === 'aprobada') ? $saldoActual : ($saldoActual - $diasTomados);

        return [
            $empleado->apellido_paterno,
            $empleado->apellido_materno,
            $empleado->nombres,
            $empleado->ci,
            $empleado->cargo,
            $empleado->fecha_ingreso ? $empleado->fecha_ingreso->format('d/m/Y') : '',
            $empleado->anos_servicio,
            $empleado->dias_correspondientes,
            (float) $saldoAntes,
            $solicitud ? $solicitud->fecha_inicio->format('d/m/Y') : '',
            $solicitud ? $solicitud->fecha_fin->format('d/m/Y') : '',
            (float) $diasTomados,
            (float) $saldoDespues,
            $solicitud ? strtoupper($solicitud->estado) : 'SIN PROGRAMAR',
            ($solicitud && $solicitud->nombre_reemplazo) ? $solicitud->nombre_reemplazo : '',
            $cargoReemplazo
        ];
    }

    public function title(): string
    {
        return $this->sede ? $this->sede->nombre : 'Plan de Vacaciones';
    }

    public function headings(): array
    {
        $year = $this->filtros['ano'] ?? date('Y');
        return [
            '1º APELLIDO',
            '2º APELLIDO',
            'NOMBRE(S)',
            'C.I.',
            'CARGO',
            'FECHA DE INGRESO',
            'AÑOS DE ANTIGUEDAD',
            'DIAS CORRESPONDE N ' . $year,
            'SALDO DE DIAS',
            'FECHA PROGRAMADA INICIO VACACION',
            'FECHA PROGRAMADA FIN VACACION',
            'DIAS TOMADOS',
            'SALDO',
            'ESTADO',
            'REEMPLAZO',
            'CARGO REEMPLAZO'
        ];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [ // Fila de cabecera de columnas
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

                // 1. Insertamos 2 filas al inicio
                $sheet->insertNewRowBefore(1, 2);

                // FILA 1: Nombre de la Sede
                $sheet->mergeCells('A1:P1');
                $nombreSede = strtoupper($this->sede ? $this->sede->nombre : 'TODAS LAS SEDES');
                $sheet->setCellValue('A1', $nombreSede);

                // FILA 2: Resumen de Filtros
                $sheet->mergeCells('A2:P2');
                $year = $this->filtros['ano'] ?? date('Y');
                $fechaDesde = $this->filtros['fecha_desde'] ?? null;
                $fechaHasta = $this->filtros['fecha_hasta'] ?? null;
                $modo = $this->filtros['modo'] ?? 'gestion';

                if ($modo === 'rango' && $fechaDesde && $fechaHasta) {
                    $resumenFiltros = "REPORTE: VACACIONES POR RANGO DE FECHAS | DEL " . date('d/m/Y', strtotime($fechaDesde)) . " AL " . date('d/m/Y', strtotime($fechaHasta));
                } else {
                    $resumenFiltros = "REPORTE: PLAN ANUAL DE VACACIONES GESTIÓN " . $year;
                }
                $sheet->setCellValue('A2', $resumenFiltros);

                // Estilo Sede (Fila 1)
                $sheet->getStyle('A1:P1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']]
                ]);
                $sheet->getRowDimension(1)->setRowHeight(35);

                // Estilo Resumen Filtros (Fila 2)
                $sheet->getStyle('A2:P2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '333333']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C8E6C9']]
                ]);
                $sheet->getRowDimension(2)->setRowHeight(25);

                // Estilo Cabeceras de Columnas (Ahora en Fila 3)
                $sheet->getRowDimension(3)->setRowHeight(40);
                $sheet->getStyle('A3:P3')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]
                ]);

                // Estilos de datos (Fila 4 en adelante)
                $lastRow = $sheet->getHighestRow();
                if ($lastRow >= 4) {
                    $sheet->getStyle('A4:P' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('D4:P' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Formatos numéricos (0.0 para que se vea el cero)
                    $sheet->getStyle('I4:I' . $lastRow)->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle('L4:L' . $lastRow)->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle('M4:M' . $lastRow)->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle('M4:M' . $lastRow)->getFont()->setBold(true);

                    // Colorear estado
                    for ($i = 4; $i <= $lastRow; $i++) {
                        $estadoCell = 'N' . $i;
                        $estado = $sheet->getCell($estadoCell)->getValue();
                        if ($estado === 'APROBADA') {
                            $sheet->getStyle($estadoCell)->getFont()->getColor()->setRGB('2E7D32');
                        } elseif ($estado === 'PENDIENTE') {
                            $sheet->getStyle($estadoCell)->getFont()->getColor()->setRGB('F57C00');
                        } elseif ($estado === 'RECHAZADA') {
                            $sheet->getStyle($estadoCell)->getFont()->getColor()->setRGB('C62828');
                        }
                    }
                }

                $sheet->freezePane('A4');
            },
        ];
    }
}

