<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request)
    {
        $user = User::create($request->validated());

        return response([
            'user' => $user,
            'token' => $user->createToken('main')->plainTextToken
        ]);
    }

    public function login(LoginUserRequest $request)
    {
        $data = $request->validated();

        $remember = $data['remember'] ?? false;
        // unset() destroys variable.
        // to avoid getting an error since remember doest not exist in users table
        unset($data['remember']);

        if (!Auth::attempt($data, $remember)) {
            return response([
                'error' => 'The Provided credentials are not correct'
            ], 422);
        }

        $user = Auth::user();
        $token = $user->createToken('main')->plainTextToken;

        return response([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function logout()
    {
        $user = Auth::user();
        // Revoke the token that was used to authenticate the current request
        $user->currentAccessToken()->delete();

        return response([
            'success' => true
        ]);
    }
}
