<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PROBANDO FERIADOS 2026 ===\n\n";

// Simular la query que hace el controlador
$ano = 2026;
$query = \App\Models\Feriado::activos();

// Filtro por año (igual que en el controlador)
$query->where(function ($q) use ($ano) {
    $q->whereYear('fecha', $ano)
        ->orWhere('es_recurrente', true);
});

echo "Query SQL:\n";
echo $query->toSql() . "\n\n";

$feriados = $query->get();

echo "Total feriados encontrados: " . $feriados->count() . "\n\n";

foreach ($feriados as $f) {
    echo "ID: {$f->id}\n";
    echo "Nombre: {$f->nombre}\n";
    echo "Fecha: {$f->fecha}\n";
    echo "Tipo: {$f->tipo}\n";
    echo "Sede ID: {$f->sede_id}\n";
    echo "Recurrente: " . ($f->es_recurrente ? 'Sí' : 'No') . "\n";
    echo "Activo: " . ($f->activo ? 'Sí' : 'No') . "\n";
    echo "---\n";
}

// Ahora probar con el filtro de sede (como lo hace el calendario)
echo "\n=== CON FILTRO DE SEDE (incluir_nacionales=true) ===\n\n";

$sedeId = 1; // Asumiendo que el empleado tiene sede_id = 1
$query2 = \App\Models\Feriado::activos();

$query2->where(function ($q) use ($ano) {
    $q->whereYear('fecha', $ano)
        ->orWhere('es_recurrente', true);
});

// Filtro con incluir_nacionales (como lo modifiqué)
$query2->where(function ($q) use ($sedeId) {
    $q->where('tipo', \App\Models\Feriado::TIPO_NACIONAL)
      ->orWhere('sede_id', $sedeId);
});

echo "Query SQL con sede:\n";
echo $query2->toSql() . "\n\n";

$feriadosConSede = $query2->get();

echo "Total feriados con filtro de sede: " . $feriadosConSede->count() . "\n\n";

foreach ($feriadosConSede as $f) {
    echo "- {$f->nombre} ({$f->fecha->format('d/m/Y')}) - Tipo: {$f->tipo}\n";
}
