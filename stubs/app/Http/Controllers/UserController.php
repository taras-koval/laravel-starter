<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Tests @see UserControllerTest
 */
class UserController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->safe()->except(['email', 'current_password', 'password', 'password_confirmation']));

        if ($request->has('email') && $request->input('email') !== $user->email) {
            $user->forceFill([
                'email' => $request->validated('email'),
                'email_verified_at' => null,
            ])->save();
            $user->sendEmailVerificationNotification();
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }

        if ($request->has('password')) {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }

        return UserResource::make($user);
    }
}
