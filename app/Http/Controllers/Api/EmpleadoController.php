<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\HistorialVacacion;
use App\Services\VacacionesService;
use App\Imports\EmpleadosImport;
use App\Exports\PlantillaEmpleadosExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmpleadoController extends Controller
{
    protected VacacionesService $vacacionesService;

    public function __construct(VacacionesService $vacacionesService)
    {
        $this->vacacionesService = $vacacionesService;
    }

    /**
     * Listar empleados con filtros
     */
    public function index(Request $request): JsonResponse
    {
        \Illuminate\Support\Facades\Log::info('EmpleadoController::index params', $request->all());
        $query = Empleado::with('sede');

        // Filtros
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('ci', 'like', "%{$buscar}%")
                    ->orWhere('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellido_paterno', 'like', "%{$buscar}%")
                    ->orWhere('apellido_materno', 'like', "%{$buscar}%");
            });
        }

        if ($request->has('saldo_negativo') && $request->boolean('saldo_negativo')) {
            $query->where('saldo_vacaciones', '<', 0);
        }

        // Filtro por rango de saldo
        if ($request->has('saldo_min')) {
            $query->where('saldo_vacaciones', '>=', $request->saldo_min);
        }

        if ($request->has('saldo_max')) {
            $query->where('saldo_vacaciones', '<=', $request->saldo_max);
        }

        // Filtro por sede
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        // Filtro por género
        if ($request->has('genero') && $request->genero !== 'todos') {
            $query->where('genero', $request->genero);
        }

        // Filtro por tipo de contrato
        if ($request->has('tipo_contrato') && $request->tipo_contrato !== 'todos') {
            $query->where('tipo_contrato', $request->tipo_contrato);
        }

        // Ordenar - por defecto por nombre, pero permite ordenar por saldo
        $ordenarPor = $request->get('ordenar_por', 'nombre');
        $ordenDir = $request->get('orden_dir', 'asc');

        if ($ordenarPor === 'saldo') {
            $query->orderBy('saldo_vacaciones', $ordenDir);
        } else {
            $query->orderBy('apellido_paterno', $ordenDir)
                ->orderBy('apellido_materno', $ordenDir)
                ->orderBy('nombres', $ordenDir);
        }

        // Paginación
        $perPage = $request->get('per_page', 15);

        \Illuminate\Support\Facades\Log::info('SQL Query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        $empleados = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $empleados,
        ]);
    }

    /**
     * Obtener empleado
     */
    public function show(int $id): JsonResponse
    {
        $empleado = Empleado::with(['solicitudes', 'historial' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(20);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $empleado,
        ]);
    }

    /**
     * Crear empleado
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'nombres' => 'required|string|max:100',
            'ci' => 'required|string|max:20|unique:empleados,ci|regex:/^[0-9]{4,10}(-[0-9]?[A-Za-z]{1,2})?$/',
            'genero' => 'nullable|in:Masculino,Femenino',
            'tipo_contrato' => 'nullable|in:completo,medio_tiempo',
            'sede_id' => 'nullable|exists:core.sedes,id_sede',
            'cargo' => 'required|string|max:100',
            'fecha_ingreso' => 'required|date',
            'saldo_vacaciones' => 'nullable|numeric',
            'activo' => 'nullable|boolean',
        ], [
            'ci.regex' => 'El CI debe contener 4-10 dígitos, opcionalmente con extensión (-LP, -SC, etc.)',
            'ci.unique' => 'Ya existe un empleado con este CI.',
        ]);

        // Valor por defecto para tipo_contrato
        if (!isset($data['tipo_contrato'])) {
            $data['tipo_contrato'] = Empleado::CONTRATO_COMPLETO;
        }

        $empleado = Empleado::create($data);

        // Registrar en historial si se estableció saldo inicial
        if (isset($data['saldo_vacaciones']) && $data['saldo_vacaciones'] != 0) {
            HistorialVacacion::registrar(
                $empleado,
                0,
                $data['saldo_vacaciones'],
                HistorialVacacion::TIPO_AJUSTE_MANUAL,
                'Saldo inicial al crear empleado',
                auth()->id()
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Empleado creado correctamente.',
            'data' => $empleado,
        ], 201);
    }

    /**
     * Actualizar empleado
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);

        $data = $request->validate([
            'apellido_paterno' => 'sometimes|required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'nombres' => 'sometimes|required|string|max:100',
            'ci' => 'sometimes|required|string|max:20|unique:empleados,ci,' . $id . '|regex:/^[0-9]{4,10}(-[0-9]?[A-Za-z]{1,2})?$/',
            'genero' => 'nullable|in:Masculino,Femenino',
            'tipo_contrato' => 'nullable|in:completo,medio_tiempo',
            'sede_id' => 'nullable|exists:core.sedes,id_sede',
            'cargo' => 'sometimes|required|string|max:100',
            'fecha_ingreso' => 'sometimes|required|date',
            'activo' => 'nullable|boolean',
        ], [
            'ci.regex' => 'El CI debe contener 4-10 dígitos, opcionalmente con extensión (-LP, -SC, etc.)',
            'ci.unique' => 'Ya existe un empleado con este CI.',
        ]);

        $empleado->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Empleado actualizado correctamente.',
            'data' => $empleado->fresh(),
        ]);
    }

    /**
     * Ajustar saldo de vacaciones manualmente
     */
    public function ajustarSaldo(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'nuevo_saldo' => 'required|numeric',
            'descripcion' => 'required|string|max:500',
        ]);

        $empleado = Empleado::findOrFail($id);

        $this->vacacionesService->ajustarSaldo(
            $empleado,
            $request->nuevo_saldo,
            $request->descripcion,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Saldo ajustado correctamente.',
            'data' => $empleado->fresh(),
        ]);
    }

    /**
     * Importar empleados desde Excel
     */
    public function importar(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'sede_id' => 'nullable|exists:core.sedes,id_sede',
        ], [
            'sede_id.exists' => 'La sede de respaldo seleccionada no existe.',
        ]);

        try {
            $import = new EmpleadosImport(auth()->id(), $request->sede_id);
            Excel::import($import, $request->file('archivo'));

            return response()->json([
                'success' => true,
                'message' => 'Importación completada.',
                'data' => [
                    'creados' => $import->getCreados(),
                    'actualizados' => $import->getActualizados(),
                    'errores' => $import->getErrores(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al importar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar empleado (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->delete();

        return response()->json([
            'success' => true,
            'message' => 'Empleado eliminado correctamente.',
        ]);
    }

    /**
     * Estadísticas de empleados
     */
    public function estadisticas(): JsonResponse
    {
        $totalActivos = Empleado::activos()->count();
        $conSaldoNegativo = Empleado::activos()->where('saldo_vacaciones', '<', 0)->count();
        $conSaldoCero = Empleado::activos()->where('saldo_vacaciones', 0)->count();
        $saldoPromedio = Empleado::activos()->avg('saldo_vacaciones');
        $totalDiasPendientes = Empleado::activos()->sum('saldo_vacaciones');

        return response()->json([
            'success' => true,
            'data' => [
                'total_activos' => $totalActivos,
                'con_saldo_negativo' => $conSaldoNegativo,
                'con_saldo_cero' => $conSaldoCero,
                'saldo_promedio' => round($saldoPromedio, 1),
                'total_dias_pendientes' => $totalDiasPendientes,
            ],
        ]);
    }

    /**
     * Descargar plantilla Excel para importar empleados
     */
    public function descargarPlantilla()
    {
        return Excel::download(new PlantillaEmpleadosExport(), 'plantilla_empleados.xlsx');
    }

    /**
     * Obtener días ocupados por solicitudes activas del empleado
     * (pendiente, pendiente_documento, aprobada)
     */
    public function diasOcupados(int $id): JsonResponse
    {
        $empleado = Empleado::findOrFail($id);

        // Obtener solicitudes activas (no rechazadas ni canceladas)
        $solicitudesActivas = $empleado->solicitudes()
            ->whereIn('estado', ['pendiente', 'pendiente_documento', 'aprobada'])
            ->with('detalles')
            ->get();

        $diasOcupados = [];

        foreach ($solicitudesActivas as $solicitud) {
            foreach ($solicitud->detalles as $detalle) {
                $fecha = $detalle->fecha instanceof \DateTime
                    ? $detalle->fecha->format('Y-m-d')
                    : (is_string($detalle->fecha) ? explode('T', $detalle->fecha)[0] : null);

                if ($fecha) {
                    $diasOcupados[] = [
                        'fecha' => $fecha,
                        'estado' => $solicitud->estado,
                        'tipo' => $detalle->tipo,
                        'solicitud_id' => $solicitud->id,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $diasOcupados,
        ]);
    }
}
