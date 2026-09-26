<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\RequestMetadataService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests @see AuthControllerTest
 */
class AuthController extends Controller
{
    /**
     * Bcrypt hash of a random string. Its cost (12) must match BCRYPT_ROUNDS,
     * otherwise login timing for unknown emails differs from existing ones.
     */
    private const string DUMMY_PASSWORD_HASH = '$2y$12$gZVi.qTXjaUDjR5waNutHemUcNl7feZjjvEMgyXjlkbCqjMzF5onK';

    public function __construct(
        private readonly RequestMetadataService $requestMetadataService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $requestMetadata = $this->requestMetadataService->getMetadata(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'timezone' => $request->validated('timezone') ?? $requestMetadata['timezone'] ?? 'UTC',
        ]);

        event(new Registered($user));
        event(new Login('sanctum', $user, false));

        $token = $user->createToken($request->input('device_name') ?? $requestMetadata['device_name']);
        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device' => $requestMetadata['device'],
            'device_type' => $requestMetadata['device_type'],
            'platform' => $requestMetadata['platform'],
            'browser' => $requestMetadata['browser'],
            'country' => $requestMetadata['country'],
            'region' => $requestMetadata['region'],
            'city' => $requestMetadata['city'],
        ])->save();

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token->plainTextToken,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        event(new Attempting('sanctum', $request->validated(), false));

        $user = User::where('email', $request->validated('email'))->first();

        // Always run exactly one hash check so unknown emails can't be detected by response time.
        $isPasswordValid = Hash::check($request->validated('password'), $user->password ?? self::DUMMY_PASSWORD_HASH);

        if (!$user || !$isPasswordValid) {
            event(new Failed('sanctum', $user, $request->validated()));
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        event(new Login('sanctum', $user, false));

        $requestMetadata = $this->requestMetadataService->getMetadata(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $deviceName = $request->input('device_name') ?? $requestMetadata['device_name'];
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName);
        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device' => $requestMetadata['device'],
            'device_type' => $requestMetadata['device_type'],
            'platform' => $requestMetadata['platform'],
            'browser' => $requestMetadata['browser'],
            'country' => $requestMetadata['country'],
            'region' => $requestMetadata['region'],
            'city' => $requestMetadata['city'],
        ])->save();

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token->plainTextToken,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('If an account exists for this email, a password reset link has been sent.'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            credentials: $request->safe()->only('email', 'password', 'password_confirmation', 'token'),
            callback: static function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__('Unable to reset password with the provided credentials.')],
            ]);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (!$user || !hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw ValidationException::withMessages(['email' => ['Invalid or expired verification link.']]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email successfully verified.']);
    }

    public function sessions(Request $request): AnonymousResourceCollection
    {
        return SessionResource::collection(
            $request->user()->activeTokens()->latest('last_used_at')->get(),
        );
    }

    public function revokeSession(Request $request, int $id): Response
    {
        $token = $request->user()->tokens()->where('id', $id)->firstOrFail();
        $token->delete();

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    public function logoutAll(Request $request): Response
    {
        $request->user()->revokeOtherTokens();

        return response()->noContent();
    }
}
