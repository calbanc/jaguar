<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\WorkerAssignments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerAssignmentController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data, private WorkerAssignments $assignments) {}

    public function index(Request $request, int $worker): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'organizacion', 'ver');
        $record = $this->data->find('trabajadores', $context, $worker);
        $date = $this->assignments->referenceDate();
        $history = $this->assignments->query($context, $worker)->orderByDesc('fecha_inicio')->orderByDesc('id')->get()->map(fn ($assignment) => $this->assignments->present($assignment));

        return response()->json(['data' => $history, 'trabajador' => $this->data->present('trabajadores', $record), 'fecha_ubicacion' => $date]);
    }

    public function store(Request $request, int $worker): JsonResponse
    {
        return $this->write($request, $worker);
    }

    public function update(Request $request, int $worker, int $assignment): JsonResponse
    {
        return $this->write($request, $worker, $assignment);
    }

    private function write(Request $request, int $worker, ?int $assignment = null): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'organizacion', 'editar');

        return DB::transaction(function () use ($request, $worker, $assignment, $context) {
            $this->data->lock($context);
            $record = $this->data->find('trabajadores', $context, $worker);
            $existing = $assignment ? $this->assignments->query($context, $worker)->where('id', $assignment)->first() : null;
            abort_if($assignment && ! $existing, 404, 'Asignación no encontrada.');
            $after = $this->assignments->save($request, $record, $existing);

            return response()->json(['data' => $after], $assignment ? 200 : 201);
        });
    }
}
