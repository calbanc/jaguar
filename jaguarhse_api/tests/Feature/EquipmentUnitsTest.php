<?php

namespace Tests\Feature;

use Tests\Support\HseDatabase;
use Tests\TestCase;

class EquipmentUnitsTest extends TestCase
{
    use HseDatabase;

    public function test_catalog_accepts_only_listed_units_and_preserves_default_unit(): void
    {
        $this->installHseFixture();
        $this->loginAsTenant($this->tenant('alpha'));
        foreach (config('hse.equipment_units') as $unit) {
            $this->postJson('/api/v1/epp_catalogo', ['codigo' => $unit, 'nombre' => 'EPP', 'unidad' => $unit])
                ->assertCreated()->assertJsonPath('data.unidad', $unit);
        }
        $id = $this->postJson('/api/v1/epp_catalogo', ['codigo' => 'DEFAULT', 'nombre' => 'Casco'])
            ->assertCreated()->assertJsonPath('data.unidad', 'unidad')->json('data.id');
        $this->postJson('/api/v1/epp_catalogo', ['codigo' => 'INVALID', 'nombre' => 'EPP', 'unidad' => 'texto libre'])
            ->assertUnprocessable()->assertJsonValidationErrors('unidad');
        $this->patchJson('/api/v1/epp_catalogo/'.$id, ['unidad' => 'otro'])->assertUnprocessable()->assertJsonValidationErrors('unidad');
        $this->patchJson('/api/v1/epp_catalogo/'.$id, ['unidad' => 'par'])->assertOk()->assertJsonPath('data.unidad', 'par');
        $this->patchJson('/api/v1/epp_catalogo/'.$id, ['nombre' => 'Guantes'])->assertOk()->assertJsonPath('data.unidad', 'par');
    }
}
