<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profileData($request));
    }

    public function updateUser(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'current_password.required_with' => 'Ingresa tu contraseña actual para cambiarla.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
        ]);

        if (! empty($data['password']) && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...(! empty($data['password']) ? ['password' => $data['password']] : []),
        ]);

        return response()->json($this->profileData($request));
    }

    public function updateEmpresa(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('empresa.editar'), 403, 'Sin permiso para editar la empresa.');

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'propietario' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rfc' => ['nullable', 'string', 'max:20'],
        ]);

        $user->empresa()->firstOrFail()->update($data);

        return response()->json($this->profileData($request));
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('empresa.editar'), 403, 'Sin permiso para editar la empresa.');

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ], [
            'logo.image'   => 'El archivo debe ser una imagen.',
            'logo.mimes'   => 'Formatos permitidos: JPG, PNG, WebP, SVG.',
            'logo.max'     => 'El logo no puede superar 2 MB.',
        ]);

        $empresa = $user->empresa()->firstOrFail();

        if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
            Storage::disk('public')->delete($empresa->logo);
        }

        $path = $request->file('logo')->store("logos/{$empresa->id}", 'public');
        $empresa->update(['logo' => $path]);

        return response()->json([
            'logo'     => $path,
            'logo_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('empresa.editar'), 403, 'Sin permiso para editar la empresa.');

        $empresa = $user->empresa()->firstOrFail();

        if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
            Storage::disk('public')->delete($empresa->logo);
        }

        $empresa->update(['logo' => null]);

        return response()->json(['ok' => true]);
    }

    public function updateSucursal(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('sucursales.editar'), 403, 'Sin permiso para editar la sucursal.');

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        Sucursal::query()
            ->whereKey($user->sucursal_id)
            ->where('empresa_id', $user->empresa_id)
            ->firstOrFail()
            ->update($data);

        return response()->json($this->profileData($request));
    }

    private function profileData(Request $request): array
    {
        $user = $request->user()->fresh([
            'empresa:id,nombre,propietario,direccion,correo,telefono,rfc,logo',
            'sucursal:id,empresa_id,nombre,direccion,correo,telefono',
        ]);
        $rol = $user->rolEnSucursal((int) $user->sucursal_id);

        $empresa = $user->empresa;

        return [
            ...$user->toArray(),
            'rol'                  => $rol?->nombre,
            'permisos'             => $user->permisosActivos(),
            'puede_editar_empresa' => $user->tienePermiso('empresa.editar'),
            'puede_editar_sucursal'=> $user->tienePermiso('sucursales.editar'),
            'stats'                => $this->statsDelMes($user),
            'empresa'              => $empresa ? array_merge($empresa->toArray(), [
                'logo_url' => $empresa->logo ? Storage::disk('public')->url($empresa->logo) : null,
            ]) : null,
        ];
    }

    private function statsDelMes($user): array
    {
        $inicio = Carbon::now()->startOfMonth();
        $fin    = Carbon::now()->endOfMonth();

        $ventasBase = Venta::where('sucursal_id', $user->sucursal_id)
            ->where('empresa_id', $user->empresa_id)
            ->whereBetween('fecha', [$inicio, $fin])
            ->where('estado', '!=', 'cancelada');

        // Ventas donde el usuario fue cajero o vendedor
        $misVentas = (clone $ventasBase)
            ->where(fn($q) => $q
                ->where('user_id', $user->id)
                ->orWhere('vendedor_id', $user->id));

        $totalVentas   = $misVentas->count();
        $montoVentas   = $misVentas->sum('total');

        $idsVentas = $misVentas->pluck('id');

        $productosVendidos = VentaDetalle::whereIn('venta_id', $idsVentas)->sum('cantidad');

        $clientesUnicos = Venta::whereIn('id', $idsVentas)
            ->whereNotNull('cliente_id')
            ->distinct('cliente_id')
            ->count('cliente_id');

        // Total ventas de la sucursal (para contexto)
        $ventasSucursal = (clone $ventasBase)->count();
        $montoSucursal  = (clone $ventasBase)->sum('total');

        return [
            'ventas_mes'          => $totalVentas,
            'monto_ventas_mes'    => (float) $montoVentas,
            'productos_vendidos'  => (int) $productosVendidos,
            'clientes_unicos'     => $clientesUnicos,
            'ventas_sucursal_mes' => $ventasSucursal,
            'monto_sucursal_mes'  => (float) $montoSucursal,
            'mes'                 => Carbon::now()->locale('es')->isoFormat('MMMM YYYY'),
        ];
    }

    public function getTicketConfig(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;
        return response()->json($empresa?->ticket_config);
    }

    public function saveTicketConfig(Request $request): JsonResponse
    {
        $request->validate(['config' => ['required', 'array']]);
        $empresa = $request->user()->empresa;
        $empresa->update(['ticket_config' => $request->input('config')]);
        return response()->json(['ok' => true]);
    }
}
