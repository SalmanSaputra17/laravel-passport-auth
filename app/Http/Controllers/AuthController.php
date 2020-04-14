<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Notifications\RegisterActivation;
use Illuminate\Support\Facades\Auth;
use Laravolt\Avatar\Avatar;
use Carbon\Carbon;
use Storage;

class AuthController extends Controller
{
    public function postRegister(RegisterRequest $request)
    {
        \DB::beginTransaction();

        try {
            $user = new User([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'activation_token' => \Str::random(60)
            ]);

            $user->save();

            $avatar = (new Avatar)->create($user->name)
                ->getImageObject()
                ->encode('png');

            Storage::put('public/avatars/' . $user->id . '/avatar.png', (string) $avatar);

            $user->notify(new RegisterActivation($user));

            \DB::commit();

            return response()->json([
                'status' => 'Success',
                'message' => 'Successfully created user !'
            ], 201);
        } catch (\Exception $e) {
            \DB::rollback();

            return response()->json([
                'status' => 'Failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getActivateUser($token)
    {
        $user = User::whereActivationToken($token)->first();

        if (!$user)
            return response()->json([
                'status' => 'Failed',
                'message' => 'This activation token is invalid.'
            ], 404);

        $user->active = true;
        $user->activation_token = '';
        $user->update();

        return $user;
    }

    public function postLogin(LoginRequest $request)
    {
        try {
            $credentials = request(['email', 'password']);
            $credentials['active'] = 1;
            $credentials['deleted_at'] = null;

            if (!Auth::attempt($credentials))
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Unauthorized'
                ], 401);

            $user = $request->user();

            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;

            if ($request->remember)
                $token->expires_at = Carbon::now()->addWeeks(1);

            $token->save();

            return response()->json([
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => Carbon::parse(
                    $tokenResult->token->expires_at
                )->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getUser(Request $request)
    {
        return response()->json($request->user());
    }

    public function getLogout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'status' => 'Success',
            'message' => 'Successfully logout'
        ], 201);
    }
}
