<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    /**
     * 處理 Google / Line OAuth Callback
     */
    public function handleProviderCallback($provider)
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();
    
        $user = User::where('email', $socialUser->getEmail())->first();
        $frontend = config('app.frontend_url', 'http://localhost:5173');
        
        if ($user) {
            $user->update([
                $provider . '_id' => $socialUser->getId()
            ]);
    
            $token = $user->createToken('auth_token')->plainTextToken;
            return redirect($frontend . "/#/oauth-success?token=$token&exists=1"
                . "&name=" . urlencode($user->name)
                . "&role=" . urlencode($user->role)
            );
        }
    
        return redirect($frontend . "/#/oauth-success?register=1"
            . "&email=" . urlencode($socialUser->getEmail())
            . "&name=" . urlencode($socialUser->getName())
            . "&provider=$provider"
            . "&provider_id=" . $socialUser->getId()
        );
        dd(Socialite::driver($provider));
    }

    /**
     * 處理 Google / Line OAuth Redirect
     */
    public function handleProviderRedirect($provider)
    {
        try{
            return Socialite::driver($provider)->redirect();
        }catch (\Exception $e) {
            \Log::error('Redirect failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Redirect failed',
                'message' => $e->getMessage()
            ], 500);
        }
        
    }

    /**
     * 前端提交表單建立新帳號
     */
    public function registerOAuthUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|confirmed',
            'provider' => 'required|string',
            'provider_id' => 'required|string',
        ]);

        $user = User::create([
            'email' => $request->email,
            'name' => $request->name,
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'google_id' => $request->provider === 'google' ? $request->provider_id : null,
            'line_id' => $request->provider === 'line' ? $request->provider_id : null,
            'role' => 'user'
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => '帳號建立成功',
            'token' => $token,
            'user' => ['name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }
}
