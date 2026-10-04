<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new AuthenticatedUserResource($request->user()),
        ]);
    }
}
