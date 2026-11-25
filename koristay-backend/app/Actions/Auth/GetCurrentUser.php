<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCurrentUser
{
    use AsAction;

    public function handle(User $user): array
    {
        $user->loadMissing('tenant');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant' => $user->tenant,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }

    public function asController(Request $request): JsonResponse
    {
        return response()->json($this->handle($request->user()));
    }
}
