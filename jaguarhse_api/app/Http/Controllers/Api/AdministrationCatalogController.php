<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdministrationCatalog;
use App\Services\ExcelExport;
use App\Services\HseAccess;
use App\Services\TableFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdministrationCatalogController extends Controller
{
    public function __construct(private AdministrationCatalog $catalog, private HseAccess $access) {}

    private function context(Request $request): object
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'ver');

        return $context;
    }

    public function index(Request $request, string $section): JsonResponse
    {
        $context = $this->context($request);
        $input = $request->validate(['q' => 'nullable|string|max:150', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $page = $this->catalog->query($section, $context, $input['q'] ?? '', app(TableFilters::class)->input($request)['columns'] ?? [])->paginate($input['per_page'] ?? 20);
        $page->setCollection($this->catalog->enrich($section, $page->getCollection(), $context));

        return response()->json($page);
    }

    public function options(Request $request): JsonResponse
    {
        $context = $this->context($request);
        $own = DB::table('rol_permisos')->where('empresa_id', $context->empresa_id)->where('rol_id', $context->rol_id)->pluck('permiso_id');
        $permissions = $this->catalog->query('permisos', $context)->get()->map(function ($permission) use ($own) {
            $permission->asignable = $own->contains($permission->id);

            return $permission;
        });
        $roles = $this->catalog->enrich('roles', $this->catalog->query('roles', $context)->get(), $context);
        foreach ($roles as $role) {
            $role->asignable = collect($role->permisos)->pluck('id')->diff($own)->isEmpty();
        }

        return response()->json(['data' => ['usuarios' => $this->catalog->query('usuarios', $context)->get(),
            'roles' => $roles, 'permisos' => $permissions, 'plataformas' => DB::table('plataformas')->get()]]);
    }

    public function export(Request $request, string $section, ExcelExport $excel): BinaryFileResponse
    {
        $context = $this->context($request);
        $this->access->authorize($context, 'administracion', 'descargar');
        $input = $request->validate(['q' => 'nullable|string|max:150']);
        $rows = $this->catalog->enrich($section, $this->catalog->query($section, $context, $input['q'] ?? '', app(TableFilters::class)->input($request)['columns'] ?? [])->get(), $context);
        $columns = AdministrationCatalog::COLUMNS[$section];
        $values = $rows->map(function ($row) use ($columns) {
            return collect(array_keys($columns))->map(fn ($key) => $key === 'activo' ? ($row->$key ? 'Activo' : 'Inactivo') : ($row->$key ?? ''))->all();
        });
        $path = $excel->create(ucwords(str_replace('-', ' ', $section)), array_values($columns), $values);

        return response()->download($path, $section.'-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }
}
