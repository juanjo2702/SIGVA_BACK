<?php
use App\Models\Feriado;
use App\Models\Sede;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Request::create('/api/admin/feriados/calendario', 'GET', ['ano' => 2026, 'sede_id' => 1]) // Asumiendo sede 1
);
echo $response->getContent();
