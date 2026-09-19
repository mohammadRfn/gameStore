<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PasswordRecoveryController extends Controller
{
    public function show()
    {
        return Inertia::render('ForgotPassword');
    }

    /** ریست رمز: فقط نام کاربری + رمز جدید؛ بدون رمز قبلی و بدون کد. */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'username'     => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:6', 'max:72', 'confirmed'],
            ],
            [
                'username.required'      => 'نام کاربری را وارد کن.',
                'new_password.required'  => 'رمز عبور جدید را وارد کن.',
                'new_password.min'       => 'رمز عبور جدید حداقل ۶ کاراکتر باشد.',
                'new_password.max'       => 'رمز عبور جدید حداکثر ۷۲ کاراکتر باشد.',
                'new_password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
            ],
        );

        $user = User::query()->where('username', trim($data['username']))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'username' => 'کاربری با این نام کاربری پیدا نشد.',
            ]);
        }

        $user->password = Hash::make($data['new_password']);
        $user->setRememberToken(Str::random(60)); // کوکی «مرا به خاطر بسپار» قبلی باطل شود
        $user->save();

        return response()->json([
            'ok'      => true,
            'message' => 'رمز عبور با موفقیت تغییر کرد.',
        ])
;
    }
}