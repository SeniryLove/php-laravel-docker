<?php

namespace App\Http\Controllers\Auth;

use Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * 處理登入認證 (支援 Email 或 使用者名稱)
     */
    public function login(Request $request)
    {
        // 1. 驗證輸入格式 [11, 12]
        $credentials = $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. 判斷輸入的是 Email 還是 Username [7, 8]
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // 3. 手動嘗試認證使用者 [8, 13]
        // 透過指定欄位尋找使用者並比對雜湊密碼 [10]
        if (Auth::attempt([$field => $request->login, 'password' => $request->password])) {
            $user = Auth::user(); // 取得已認證之使用者 [14]

            // 4. 產生 Sanctum Token [15-17]
            $token = $user->createToken('auth_token')->plainTextToken;

            // 回傳 JSON 並處理 UTF-8 編碼問題 [18, 19]
            return response()->json([
                'message' => '登入成功',
                'token'   => $token,
                'user'    => [
                    'name' => $user->name,
                    'role' => $user->role, // 用於前端 Pinia 判斷權限 [5, 16, 20]
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }

        // 認證失敗 [13]
        return response()->json(['message' => '認證失敗，請檢查帳號密碼'], 401, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 取得當前登入者資訊
     */
    public function me(Request $request)
    {
        // 透過 Request 實例回傳已認證之使用者 [14]
        return response()->json($request->user());
    }

    /**
     * 登出並撤銷 Token
     */
    public function logout(Request $request)
    {
        // 清除當前使用的 Access Token [19, 21]
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => '已成功登出'], 200, [], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 前端提交表單建立新帳號
     */
    public function registerUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create([
            'email' => $request->email,
            'name' => $request->name,
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'google_id' => null,
            'line_id' => null,
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
