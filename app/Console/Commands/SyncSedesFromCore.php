<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncSedesFromCore extends Command
{
    protected $signature = 'sedes:sync-from-core {--include-campus : Incluye tambien los campus/subsedes del core}';

    protected $description = 'Sincroniza el catalogo de sedes de SIGVA usando SIGETH como fuente maestra';

    /**
     * Departamentos usados por SIGVA para las sedes principales.
     *
     * @var array<int, string>
     */
    protected array $departamentosBase = [
        1 => 'LA PAZ',
        2 => 'LA PAZ',
        3 => 'COCHABAMBA',
        4 => 'COCHABAMBA',
        5 => 'BENI',
        6 => 'SANTA CRUZ',
        7 => 'SANTA CRUZ',
        8 => 'PANDO',
        9 => 'NACIONAL',
    ];

    public function handle(): int
    {
        $this->info('Sincronizando sedes desde la conexion core...');

        $coreSedes = collect(DB::connection('core')
            ->table('sedes')
            ->select('id_sede', 'nombre', 'sigla', 'activo')
            ->orderBy('id_sede')
            ->get());

        if ($coreSedes->isEmpty()) {
            $this->error('No se encontraron sedes en la conexion core.');
            return Command::FAILURE;
        }

        if (!$this->option('include-campus')) {
            $coreSedes = $coreSedes->where('id_sede', '<=', 9)->values();
        }

        $now = Carbon::now();

        $payload = $coreSedes->map(function ($sede) use ($now) {
            return [
                'id' => (int) $sede->id_sede,
                'nombre' => mb_strtoupper((string) $sede->nombre, 'UTF-8'),
                'abreviacion' => mb_strtoupper((string) $sede->sigla, 'UTF-8'),
                'departamento' => $this->resolverDepartamento((int) $sede->id_sede, (string) $sede->nombre),
                'activo' => (bool) $sede->activo,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        DB::transaction(function () use ($payload) {
            DB::table('sedes')->upsert(
                $payload,
                ['id'],
                ['nombre', 'abreviacion', 'departamento', 'activo', 'updated_at']
            );
        });

        $this->table(
            ['ID', 'Nombre', 'Sigla', 'Departamento', 'Activo'],
            collect($payload)->map(fn (array $sede) => [
                $sede['id'],
                $sede['nombre'],
                $sede['abreviacion'],
                $sede['departamento'],
                $sede['activo'] ? 'SI' : 'NO',
            ])->all()
        );

        $this->info('Sincronizacion completada correctamente.');

        return Command::SUCCESS;
    }

    protected function resolverDepartamento(int $id, string $nombre): string
    {
        if (isset($this->departamentosBase[$id])) {
            return $this->departamentosBase[$id];
        }

        return mb_strtoupper(trim($nombre), 'UTF-8');
    }
}
