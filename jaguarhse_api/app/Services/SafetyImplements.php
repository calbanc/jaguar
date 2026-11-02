<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SafetyImplements
{
    public function nextDate(string $date, int $amount, string $unit): string
    {
        $start = CarbonImmutable::parse($date);

        return (match ($unit) {
            'dias' => $start->addDays($amount),
            'meses' => $start->addMonthsNoOverflow($amount),
            'anios' => $start->addYearsNoOverflow($amount),
        })->toDateString();
    }

    public function schedule(object $item, object $installation, string $baseDate, ?int $responsible): int
    {
        $catalog = DB::table('implementos_catalogo')->where('empresa_id', $item->empresa_id)->where('id', $item->catalogo_id)->first();

        return DB::table('implemento_inspecciones')->insertGetId([
            'empresa_id' => $item->empresa_id, 'implemento_id' => $item->id, 'instalacion_id' => $installation->id,
            'fecha_programada' => $this->nextDate($baseDate, $catalog->periodicidad_cantidad, $catalog->periodicidad_unidad),
            'responsable_id' => $responsible, 'periodicidad_cantidad' => $catalog->periodicidad_cantidad,
            'periodicidad_unidad' => $catalog->periodicidad_unidad, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function polygon(?array $points): ?array
    {
        if ($points === null || $points === []) {
            return null;
        }
        $unique = array_unique(array_map(fn ($point) => json_encode($point), $points));
        if (count($unique) < 3) {
            throw ValidationException::withMessages(['coordenadas' => 'El área requiere al menos tres puntos distintos.']);
        }
        if ($points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }
        $area = 0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $area += $points[$i][0] * $points[$i + 1][1] - $points[$i + 1][0] * $points[$i][1];
        }
        if (abs($area) < 0.000000000001) {
            throw ValidationException::withMessages(['coordenadas' => 'Los puntos deben delimitar una superficie.']);
        }

        return ['type' => 'Polygon', 'coordinates' => [$points]];
    }
}
