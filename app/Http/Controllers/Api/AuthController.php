<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\TransientToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => '入力データに誤りがあります',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 同じメールアドレスの既存ユーザーの全トークンを削除（念のため）
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            $existingUser->tokens()->delete();
        }

        // 全ユーザーの既存トークンをクリア（一時的なデバッグ対応）
        //DB::table('personal_access_tokens')->truncate();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'company_name' => $request->company_name,
        ]);

        // 新しいトークンを作成（ユニークなトークン名を使用）
        $tokenName = 'auth_token_' . $user->id . '_' . time();
        $token = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;

        // デバッグログ
        Log::info('User registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'token_prefix' => substr($token, 0, 10),
            'token_name' => $tokenName
        ]);

        return response()->json([
            'message' => 'ユーザー登録が完了しました',
            'user' => $user->fresh(), // 最新の情報を取得
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => '入力データに誤りがあります',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // 既存のトークンをすべて削除（同一ユーザーの多重ログイン防止）
        $user->tokens()->delete();

        // 新しいトークンを作成（ユニークなトークン名を使用）
        $tokenName = 'auth_token_' . $user->id . '_' . time();
        $token = $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;

        // デバッグログ
        Log::info('User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'token_prefix' => substr($token, 0, 10),
            'token_name' => $tokenName
        ]);

        return response()->json([
            'message' => 'ログインしました',
            'user' => $user->fresh(), // 最新の情報を取得
            'token' => $token,
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $token = $request->bearerToken();

        // トークンの詳細情報を取得
        $currentToken = $user->currentAccessToken();

        // TransientToken かどうかをチェック
        $isTransientToken = $currentToken instanceof TransientToken;

        // デバッグログ
        Log::info('User info requested', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'token_prefix' => $token ? substr($token, 0, 10) : 'none',
            'is_transient_token' => $isTransientToken,
            'token_id' => $isTransientToken ? 'transient' : ($currentToken ? $currentToken->id : 'none'),
            'token_name' => $isTransientToken ? 'transient' : ($currentToken ? $currentToken->name : 'none')
        ]);

        return response()->json([
            'user' => $user,
            'debug' => [
                'token_user_id' => $user->id,
                'token_prefix' => $token ? substr($token, 0, 10) : 'none',
                'is_transient_token' => $isTransientToken,
                'token_id' => $isTransientToken ? 'transient' : ($currentToken ? $currentToken->id : 'none'),
                'current_token_tokenable_id' => $isTransientToken ? $user->id : ($currentToken ? $currentToken->tokenable_id : 'none')
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $isTransientToken = $currentToken instanceof TransientToken;

        // APIトークンを削除
        if (!$isTransientToken && $currentToken) {
            $currentToken->delete();
        }

        // セッションを手動でクリア
        if (session()->isStarted()) {
            session()->flush();
            session()->regenerate();
        }

        Log::info('User logged out', [
            'user_id' => $user->id,
            'email' => $user->email,
            'was_transient_token' => $isTransientToken,
        ]);

        return response()->json([
            'message' => 'ログアウトしました',
        ]);
    }

    // 全デバイスからログアウト（オプション）
    public function logoutAll(Request $request)
    {
        // ユーザーのすべてのトークンを削除
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'すべてのデバイスからログアウトしました',
        ]);
    }
}
