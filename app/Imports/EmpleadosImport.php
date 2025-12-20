<?php

namespace App\Imports;

use App\Models\Empleado;
use App\Models\HistorialVacacion;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Carbon\Carbon;

class EmpleadosImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected int $creados = 0;
    protected int $actualizados = 0;
    protected array $errores = [];
    protected ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * Mapeo de encabezados del Excel a campos
     */
    public function model(array $row)
    {
        // Mapear columnas del Excel según formato proporcionado
        $apellidoPaterno = $row['1_apellido'] ?? $row['1o_apellido'] ?? $row['primer_apellido'] ?? $row['apellido_paterno'] ?? null;
        $apellidoMaterno = $row['2_apellido'] ?? $row['2o_apellido'] ?? $row['segundo_apellido'] ?? $row['apellido_materno'] ?? null;
        $nombres = $row['nombres'] ?? $row['nombre'] ?? null;
        $ci = $row['ci'] ?? $row['c_i'] ?? $row['cedula'] ?? null;
        $cargo = $row['cargo'] ?? null;
        $fechaIngreso = $row['fecha_de_ingreso'] ?? $row['fecha_ingreso'] ?? null;
        $saldoDias = $row['saldo_de_dias'] ?? $row['saldo_dias'] ?? $row['saldo'] ?? 0;

        // Nuevos campos: género, tipo de contrato y sede
        $genero = $this->normalizarGenero($row['genero'] ?? $row['sexo'] ?? null);
        $tipoContrato = $this->normalizarTipoContrato($row['tipo_contrato'] ?? $row['contrato'] ?? $row['tipo'] ?? null);
        $sede = $row['sede'] ?? $row['sucursal'] ?? $row['oficina'] ?? null;

        if (!$ci || !$apellidoPaterno || !$nombres) {
            $this->errores[] = "Fila con datos incompletos: CI={$ci}";
            return null;
        }

        // Limpiar CI (quitar espacios y caracteres especiales)
        $ci = preg_replace('/[^0-9A-Za-z]/', '', trim($ci));

        // Parsear fecha
        try {
            if (is_numeric($fechaIngreso)) {
                // Excel serial date
                $fechaIngreso = Carbon::createFromFormat('Y-m-d', gmdate('Y-m-d', ($fechaIngreso - 25569) * 86400));
            } else {
                $fechaIngreso = Carbon::parse($fechaIngreso);
            }
        } catch (\Exception $e) {
            $this->errores[] = "Error en fecha para CI {$ci}: {$fechaIngreso}";
            return null;
        }

        // Buscar empleado existente por CI
        $empleadoExistente = Empleado::where('ci', $ci)->first();

        if ($empleadoExistente) {
            // Actualizar empleado existente
            $saldoAnterior = $empleadoExistente->saldo_vacaciones;

            $empleadoExistente->update([
                'apellido_paterno' => trim($apellidoPaterno),
                'apellido_materno' => $apellidoMaterno ? trim($apellidoMaterno) : null,
                'nombres' => trim($nombres),
                'cargo' => $cargo ? trim($cargo) : $empleadoExistente->cargo,
                'fecha_ingreso' => $fechaIngreso,
                'saldo_vacaciones' => floatval($saldoDias),
                'genero' => $genero ?? $empleadoExistente->genero,
                'tipo_contrato' => $tipoContrato ?? $empleadoExistente->tipo_contrato,
                'sede' => $sede ? trim($sede) : $empleadoExistente->sede,
                'activo' => true,
            ]);

            // Registrar en historial si cambió el saldo
            if ($saldoAnterior != floatval($saldoDias)) {
                HistorialVacacion::registrar(
                    $empleadoExistente,
                    $saldoAnterior,
                    floatval($saldoDias) - $saldoAnterior,
                    HistorialVacacion::TIPO_IMPORTACION,
                    'Actualización por importación de Excel',
                    $this->userId
                );
            }

            $this->actualizados++;
            return null; // No crear nuevo
        }

        // Crear nuevo empleado
        $empleado = new Empleado([
            'apellido_paterno' => trim($apellidoPaterno),
            'apellido_materno' => $apellidoMaterno ? trim($apellidoMaterno) : null,
            'nombres' => trim($nombres),
            'ci' => $ci,
            'cargo' => $cargo ? trim($cargo) : 'Sin cargo',
            'fecha_ingreso' => $fechaIngreso,
            'saldo_vacaciones' => floatval($saldoDias),
            'genero' => $genero,
            'tipo_contrato' => $tipoContrato ?? Empleado::CONTRATO_COMPLETO,
            'sede' => $sede ? trim($sede) : null,
            'activo' => true,
        ]);

        $this->creados++;

        return $empleado;
    }

    /**
     * Normaliza el valor de género del Excel
     */
    protected function normalizarGenero(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $valor = strtoupper(trim($valor));

        // Mapear diferentes formatos a los valores válidos
        if (in_array($valor, ['M', 'MASCULINO', 'HOMBRE', 'MALE', 'H'])) {
            return Empleado::GENERO_MASCULINO;
        }

        if (in_array($valor, ['F', 'FEMENINO', 'MUJER', 'FEMALE'])) {
            return Empleado::GENERO_FEMENINO;
        }

        return null;
    }

    /**
     * Normaliza el valor de tipo de contrato del Excel
     */
    protected function normalizarTipoContrato(?string $valor): ?string
    {
        if (!$valor) {
            return Empleado::CONTRATO_COMPLETO; // Default
        }

        $valor = strtoupper(trim($valor));

        // Mapear diferentes formatos a los valores válidos
        if (in_array($valor, ['COMPLETO', 'TIEMPO COMPLETO', 'FULL', 'FULL TIME', 'TC', 'T.C.'])) {
            return Empleado::CONTRATO_COMPLETO;
        }

        if (in_array($valor, ['MEDIO TIEMPO', 'MEDIO_TIEMPO', 'MEDIO', 'PART TIME', 'PARCIAL', 'MT', 'M.T.'])) {
            return Empleado::CONTRATO_MEDIO_TIEMPO;
        }

        return Empleado::CONTRATO_COMPLETO;
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            '*.ci' => 'nullable',
            '*.c_i' => 'nullable',
        ];
    }

    public function getCreados(): int
    {
        return $this->creados;
    }

    public function getActualizados(): int
    {
        return $this->actualizados;
    }

    public function getErrores(): array
    {
        return $this->errores;
    }
}
