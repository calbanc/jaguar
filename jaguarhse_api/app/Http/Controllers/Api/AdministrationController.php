<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailVerification;
use App\Services\HseAccess;
use App\Services\HseData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdministrationController extends Controller
{
    public function __construct(private HseData $data, private HseAccess $access) {}

    public function role(Request $request, ?int $id = null): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', $id ? 'editar' : 'crear');
        $input = $request->validate(['nombre' => ['required', 'string', 'max:80', Rule::unique('roles')->where('empresa_id', $context->empresa_id)->ignore($id)],
            'descripcion' => 'nullable|string|max:255', 'activo' => 'sometimes|boolean', 'permisos' => 'present|array', 'permisos.*' => 'integer|distinct']);

        return DB::transaction(function () use ($request, $input, $context, $id) {
            $this->data->lock($context);
            $before = $id ? $this->data->find('roles', $context, $id) : null;
            abort_if($id === $context->rol_id, 422, 'No modifique el rol de su propia sesión.');
            $ownPermissions = $this->data->query('rol_permisos', $context)->where('rol_id', $context->rol_id)->pluck('permiso_id')->all();
            abort_if(array_diff($input['permisos'], $ownPermissions) !== [], 403, 'Solo puede delegar permisos que posee.');
            if ($before) {
                $before->permisos = $this->data->query('rol_permisos', $context)->where('rol_id', $before->id)->pluck('permiso_id')->all();
            }
            if (isset($input['activo']) && ! $input['activo']) {
                $this->access->authorize($context, 'administracion', 'eliminar');
            }
            $values = collect($input)->except('permisos')->all();
            $role = $id ? $this->data->update('roles', $context, $id, $values) : $this->data->insert('roles', $context, $values);
            $this->data->query('rol_permisos', $context)->where('rol_id', $role->id)->delete();
            foreach ($input['permisos'] as $permission) {
                $this->data->insert('rol_permisos', $context, ['rol_id' => $role->id, 'permiso_id' => $permission]);
            }
            $role->permisos = $input['permisos'];
            if ($before) {
                $this->revokeRoleSessions($context, $role->id);
            }
            $this->data->audit($request, 'roles', $before, $role, 'configurar_rol');

            return response()->json(['data' => $role], $id ? 200 : 201);
        });
    }

    public function member(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'crear');
        $input = $request->validate(['nombre' => 'required|string|max:150', 'email' => 'required|email|max:190|unique:usuarios,email',
            'password' => 'required|string|min:12|max:255', 'rol_id' => 'nullable|integer',
            'plataforma_id' => 'required_with:rol_id|integer|exists:plataformas,id']);

        $result = DB::transaction(function () use ($request, $context, $input) {
            $this->data->lock($context);
            if (isset($input['rol_id'])) {
                $this->assignableRole($context, $input['rol_id']);
            }
            $limit = $context->contrato->limite_usuarios;
            abort_if($limit !== null && $this->data->query('miembros_empresa', $context)->where('activo', true)->count() >= $limit, 422, 'Límite de usuarios alcanzado.');
            $user = DB::table('usuarios')->insertGetId(['nombre' => $input['nombre'], 'email' => $input['email'],
                'password_hash' => Hash::make($input['password']), 'created_at' => now(), 'updated_at' => now()]);
            $member = $this->data->insert('miembros_empresa', $context, ['usuario_id' => $user]);
            if (isset($input['rol_id'])) {
                $this->data->insert('accesos_aplicacion', $context, ['miembro_id' => $member->id,
                    'rol_id' => $input['rol_id'], 'plataforma_id' => $input['plataforma_id'], 'activo' => true]);
            }
            $this->data->audit($request, 'miembros_empresa', null, $member, 'crear_usuario');

            return ['member' => $member, 'user_id' => $user];
        });
        $verification = app(EmailVerification::class);
        $status = $verification->send($result['user_id']);

        return response()->json(['data' => $result['member'], 'verification_email_sent' => $status === 'sent',
            'message' => $verification->registrationMessage($status)], 201);
    }

    public function members(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'ver');
        $input = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);
        $members = DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.empresa_id', $context->empresa_id)->select('m.*', 'u.nombre', 'u.email')
            ->orderBy('m.id')->paginate($input['per_page'] ?? 25);

        return response()->json($members);
    }

    public function memberStatus(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', $request->boolean('activo') ? 'editar' : 'eliminar');
        $input = $request->validate(['activo' => 'required|boolean']);

        return DB::transaction(function () use ($request, $context, $input, $id) {
            $this->data->lock($context);
            $before = $this->data->find('miembros_empresa', $context, $id);
            abort_if($id === $context->miembro_id && ! $input['activo'], 422, 'No desactive su propia membresía.');
            $limit = $context->contrato->limite_usuarios;
            if (! $before->activo && $input['activo'] && $limit !== null) {
                abort_if($this->data->query('miembros_empresa', $context)->where('activo', true)->count() >= $limit, 422, 'Límite de usuarios alcanzado.');
            }
            $after = $this->data->update('miembros_empresa', $context, $id, $input);
            if (! $input['activo']) {
                $accessIds = $this->data->query('accesos_aplicacion', $context)->where('miembro_id', $id)->pluck('id');
                $this->data->query('sesiones', $context)->whereIn('acceso_id', $accessIds)->update(['revocado_at' => now(), 'updated_at' => now()]);
            }
            $this->data->audit($request, 'miembros_empresa', $before, $after, 'estado_membresia');

            return response()->json(['data' => $after]);
        });
    }

    public function access(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $input = $request->validate(['miembro_id' => 'required|integer', 'rol_id' => 'required|integer',
            'plataforma_id' => 'required|integer|exists:plataformas,id', 'activo' => 'required|boolean']);

        return DB::transaction(function () use ($request, $context, $input) {
            $this->data->lock($context);
            $member = $this->data->find('miembros_empresa', $context, $input['miembro_id']);
            abort_if($input['activo'] && ! $member->activo, 422, 'El usuario está inactivo.');
            $role = $this->data->find('roles', $context, $input['rol_id']);
            abort_if($input['activo'] && ! $role->activo, 422, 'Rol inactivo.');
            $own = $this->data->query('rol_permisos', $context)->where('rol_id', $context->rol_id)->pluck('permiso_id')->all();
            $assigned = $this->data->query('rol_permisos', $context)->where('rol_id', $role->id)->pluck('permiso_id')->all();
            abort_if(array_diff($assigned, $own) !== [], 403, 'No puede asignar un rol con más permisos que el suyo.');
            $before = $this->data->query('accesos_aplicacion', $context)->where('miembro_id', $input['miembro_id'])->where('plataforma_id', $input['plataforma_id'])->first();
            $this->access->authorize($context, 'administracion', $request->isMethod('delete') ? 'eliminar' : ($before ? 'editar' : 'crear'));
            if (! $input['activo']) {
                $this->access->authorize($context, 'administracion', 'eliminar');
            }
            abort_if($before && $before->id === $context->acceso_id && (! $input['activo'] || $input['rol_id'] !== $before->rol_id), 422, 'No modifique el rol ni desactive el acceso de su propia sesión.');
            $after = $before ? $this->data->update('accesos_aplicacion', $context, $before->id, $input) : $this->data->insert('accesos_aplicacion', $context, $input);
            if ($before) {
                $this->data->query('sesiones', $context)->where('acceso_id', $before->id)->update(['revocado_at' => now(), 'updated_at' => now()]);
            }
            $this->data->audit($request, 'accesos_aplicacion', $before, $after, 'configurar_acceso');

            return response()->json(['data' => $after]);
        });
    }

    private function assignableRole(object $context, int $id): object
    {
        $role = $this->data->find('roles', $context, $id);
        abort_unless($role->activo, 422, 'Rol inactivo.');
        $own = $this->data->query('rol_permisos', $context)->where('rol_id', $context->rol_id)->pluck('permiso_id')->all();
        $assigned = $this->data->query('rol_permisos', $context)->where('rol_id', $id)->pluck('permiso_id')->all();
        abort_if(array_diff($assigned, $own) !== [], 403, 'No puede asignar un rol con más permisos que el suyo.');

        return $role;
    }

    private function revokeRoleSessions(object $context, int $role): void
    {
        $accesses = $this->data->query('accesos_aplicacion', $context)->where('rol_id', $role)->pluck('id');
        $this->data->query('sesiones', $context)->whereIn('acceso_id', $accesses)->whereNull('revocado_at')
            ->update(['revocado_at' => now(), 'updated_at' => now()]);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', $request->isMethod('delete') ? 'eliminar' : 'editar');
        $input = $request->validate(['nombre' => 'sometimes|required|string|max:150', 'email' => 'sometimes|required|email|max:190',
            'password' => 'sometimes|required|string|min:12|max:255', 'activo' => 'sometimes|required|boolean']);

        $result = DB::transaction(function () use ($request, $context, $id, $input) {
            $this->data->lock($context);
            $member = $this->data->find('miembros_empresa', $context, $id);
            $user = DB::table('usuarios')->where('id', $member->usuario_id)->lockForUpdate()->first();
            $before = (object) ['id' => $id, 'nombre' => $user->nombre, 'email' => $user->email, 'activo' => $member->activo];
            $identity = collect($input)->only(['nombre', 'email'])->all();
            $changed = isset($input['password']) || collect($identity)->contains(fn ($value, $key) => $value !== $user->$key);
            abort_if($changed && DB::table('miembros_empresa')->where('usuario_id', $user->id)->where('empresa_id', '!=', $context->empresa_id)->exists(), 422,
                'Este usuario pertenece a varias empresas. Sus datos de identidad requieren una actualización central.');
            if (isset($input['email'])) {
                validator($input, ['email' => [Rule::unique('usuarios', 'email')->ignore($user->id)]])->validate();
            }
            if (array_key_exists('activo', $input)) {
                if (! $input['activo']) {
                    $this->access->authorize($context, 'administracion', 'eliminar');
                }
                abort_if($id === $context->miembro_id && ! $input['activo'], 422, 'No desactive su propia membresía.');
                $limit = $context->contrato->limite_usuarios;
                if (! $member->activo && $input['activo'] && $limit !== null) {
                    abort_if($this->data->query('miembros_empresa', $context)->where('activo', true)->count() >= $limit, 422, 'Límite de usuarios alcanzado.');
                }
                $this->data->update('miembros_empresa', $context, $id, ['activo' => $input['activo']]);
            }
            $emailChanged = isset($input['email']) && $input['email'] !== $user->email;
            if ($emailChanged) {
                $identity['email_verified_at'] = null;
            }
            if (isset($input['password'])) {
                $identity['password_hash'] = Hash::make($input['password']);
            }
            if ($identity !== []) {
                DB::table('usuarios')->where('id', $user->id)->update($identity + ['updated_at' => now()]);
            }
            if ($changed || (array_key_exists('activo', $input) && ! $input['activo'])) {
                $accesses = $this->data->query('accesos_aplicacion', $context)->where('miembro_id', $id)->pluck('id');
                $this->data->query('sesiones', $context)->whereIn('acceso_id', $accesses)->update(['revocado_at' => now(), 'updated_at' => now()]);
            }
            $after = (object) ['id' => $id, 'nombre' => $input['nombre'] ?? $user->nombre,
                'email' => $input['email'] ?? $user->email, 'activo' => $input['activo'] ?? $member->activo];
            $this->data->audit($request, 'miembros_empresa', $before, $after, 'editar_usuario');

            return ['data' => $after, 'user_id' => $user->id, 'email_changed' => $emailChanged];
        });
        $response = ['data' => $result['data']];
        if ($result['email_changed']) {
            $verification = app(EmailVerification::class);
            $status = $verification->send($result['user_id']);
            $response['verification_email_sent'] = $status === 'sent';
            $response['message'] = $verification->registrationMessage($status);
        }

        return response()->json($response);
    }

    public function deactivateUser(Request $request, int $id): JsonResponse
    {
        $request->replace(['activo' => false]);

        return $this->updateUser($request, $id);
    }

    public function deactivateRole(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'eliminar');

        return DB::transaction(function () use ($request, $context, $id) {
            $this->data->lock($context);
            $before = $this->data->find('roles', $context, $id);
            abort_if($id === $context->rol_id, 422, 'No desactive el rol de su propia sesión.');
            $after = $this->data->update('roles', $context, $id, ['activo' => false]);
            $this->revokeRoleSessions($context, $id);
            $this->data->audit($request, 'roles', $before, $after, 'desactivar_rol');

            return response()->json(['data' => $after]);
        });
    }

    public function createAccess(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'crear');
        $input = $request->validate(['miembro_id' => 'required|integer', 'plataforma_id' => 'required|integer']);

        return DB::transaction(function () use ($request, $context, $input) {
            $this->data->lock($context);
            abort_if($this->data->query('accesos_aplicacion', $context)->where('miembro_id', $input['miembro_id'])
                ->where('plataforma_id', $input['plataforma_id'])->exists(), 422,
                'Este usuario ya tiene un acceso en esa plataforma. Edita la asignación existente.');

            return $this->access($request);
        });
    }

    public function updateAccess(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', $request->isMethod('delete') ? 'eliminar' : 'editar');
        $before = $this->data->find('accesos_aplicacion', $context, $id);
        $request->merge(['miembro_id' => $before->miembro_id, 'plataforma_id' => $before->plataforma_id]);

        return $this->access($request);
    }

    public function deactivateAccess(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'eliminar');
        $before = $this->data->find('accesos_aplicacion', $context, $id);
        $request->replace(['rol_id' => $before->rol_id, 'activo' => false]);

        return $this->updateAccess($request, $id);
    }

    public function rolePermission(Request $request, ?int $id = null): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', $id ? 'editar' : 'crear');
        $input = $request->validate(['rol_id' => 'required|integer', 'permiso_id' => 'required|integer']);

        return DB::transaction(function () use ($request, $context, $id, $input) {
            $this->data->lock($context);
            $before = $id ? $this->data->find('rol_permisos', $context, $id) : null;
            $this->data->find('roles', $context, $input['rol_id']);
            abort_if($input['rol_id'] === $context->rol_id || ($before && $before->rol_id === $context->rol_id), 422, 'No modifique los permisos del rol de su propia sesión.');
            abort_unless($this->data->query('rol_permisos', $context)->where('rol_id', $context->rol_id)->where('permiso_id', $input['permiso_id'])->exists(), 403, 'Solo puede delegar permisos que posee.');
            $duplicate = $this->data->query('rol_permisos', $context)->where('rol_id', $input['rol_id'])->where('permiso_id', $input['permiso_id']);
            if ($id) {
                $duplicate->where('id', '!=', $id);
            }
            abort_if($duplicate->exists(), 422, 'El rol ya tiene este permiso.');
            $after = $id ? $this->data->update('rol_permisos', $context, $id, $input) : $this->data->insert('rol_permisos', $context, $input);
            foreach (array_unique([$input['rol_id'], $before?->rol_id ?? $input['rol_id']]) as $role) {
                $this->revokeRoleSessions($context, $role);
            }
            $this->data->audit($request, 'rol_permisos', $before, $after, 'asignar_permiso');

            return response()->json(['data' => $after], $id ? 200 : 201);
        });
    }

    public function deleteRolePermission(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'administracion', 'eliminar');

        return DB::transaction(function () use ($request, $context, $id) {
            $this->data->lock($context);
            $before = $this->data->find('rol_permisos', $context, $id);
            abort_if($before->rol_id === $context->rol_id, 422, 'No modifique los permisos del rol de su propia sesión.');
            $this->data->query('rol_permisos', $context)->where('id', $id)->delete();
            $this->revokeRoleSessions($context, $before->rol_id);
            $this->data->audit($request, 'rol_permisos', $before, (object) ['id' => $id, 'retirado' => true], 'retirar_permiso');

            return response()->json(['message' => 'Permiso retirado del rol.']);
        });
    }
}
