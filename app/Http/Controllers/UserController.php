<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UserController
{
    public function buscarVendedores(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = trim((string) $request->get('q', ''));

        $items = User::query()
            ->where('empresa_id', $user->empresa_id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items);
    }
}
