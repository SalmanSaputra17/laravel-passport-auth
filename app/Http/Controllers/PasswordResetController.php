<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\PasswordReset;
use App\Notifications\PasswordResetRequest;
use App\Notifications\PasswordResetSuccess;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    public function postCreate(Request $request)
    {
        $request->validate([
            'email' => 'required|email|string'
        ]);

        $user = User::whereEmail($request->email)->first();

        if (!$user)
            return response()->json([
                'status' => 'Failed',
                'message' => 'We can\'t find a user with that e-mail address.'
            ], 404);
    
        $passwordReset = PasswordReset::updateOrCreate([
            'email' => $user->email
        ], [
            'email' => $user->email,
            'token' => \Str::random(60),
        ]);

        if ($user && $passwordReset)
            $user->notify(new PasswordResetRequest($passwordReset->token));

        return response()->json([
            'message' => 'We have e-mailed your password reset link!'
        ]);
    }

    public function getFind($token)
    {
        $passwordReset = PasswordReset::whereToken($token)->first();

        if (!$passwordReset)
            return response()->json([
                'status' => 'Failed',
                'message' => 'This password reset token is invalid.'
            ], 404);

        if (Carbon::parse($passwordReset->updated_at)->addMinutes(720)->isPast()) {
            $passwordReset->delete();

            return response()->json([
                'message' => 'This password reset token is invalid.'
            ], 404);
        }

        return response()->json($passwordReset);
    }

    public function postReset(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|confirmed',
            'token' => 'required|string'
        ]);

        $passwordReset = PasswordReset::where([
                            ['token', $request->token],
                            ['email', $request->email]
                        ])->first();

        if (!$passwordReset)
            return response()->json([
                'status' => 'Failed',
                'message' => 'This password reset token is invalid.'
            ], 404);

        $user = User::whereEmail($passwordReset->email)->first();

        if (!$user)
            return response()->json([
                'status' => 'Failed',
                'message' => 'We can\'t find a user with that e-mail address.'
            ], 404);

        $user->password = bcrypt($request->password);
        $user->save();

        $passwordReset->delete();

        $user->notify(new PasswordResetSuccess($passwordReset));

        return response()->json($user);
    }
}
