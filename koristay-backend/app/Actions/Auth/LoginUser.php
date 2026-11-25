<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Validation\ValidationException;

class LoginUser
{
    use AsAction;

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Handle the action.
     *
     * @param  \Lorisleiva\Actions\ActionRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(string $email, string $password, ?string $deviceName = null): JsonResponse
    {
        $user = User::with('tenant')->where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if($user->tenant && !$user->tenant->is_active) {
            throw ValidationException::withMessages([
                'email' => ['The tenant associated with this user is not active.'],
            ]);
        }

        $token = $user->createToken($deviceName ?? 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant' => $user->tenant,
            ]
        ]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->handle(
            email : $validated['email'],
            password : $validated['password'],
            deviceName : $validated['device_name'] ?? null
        );

        return $result;
    }
}
