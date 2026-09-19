<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /** نام کاربری فعلی */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'ok'       => true,
            'username' => $request->user()->username,
        ]);
    }

    /**
     * تغییر نام کاربری و/یا رمز عبور.
     * حالت عادی: رمز فعلی لازم است.
     * حالت force (بازنویسی): رمز فعلی لازم نیست، ولی رمز جدید الزامی است.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $force = $request->boolean('force');

        $data = $request->validate(
            [
                'force'            => ['sometimes', 'boolean'],
                'current_password' => [$force ? 'nullable' : 'required', 'string'],
                'username'         => [
                    'required', 'string', 'min:3', 'max:50', 'regex:/^\S+$/u',
                    Rule::unique('users', 'username')->ignore($user->id),
                ],
                'new_password'     => [$force ? 'required' : 'nullable', 'string', 'min:6', 'max:72', 'confirmed'],
            ],
            [
                'current_password.required' => 'رمز عبور فعلی را وارد کن.',
                'username.required'         => 'نام کاربری نمی‌تواند خالی باشد.',
                'username.min'              => 'نام کاربری حداقل ۳ کاراکتر باشد.',
                'username.max'              => 'نام کاربری حداکثر ۵۰ کاراکتر باشد.',
                'username.regex'            => 'نام کاربری نباید فاصله داشته باشد.',
                'username.unique'           => 'این نام کاربری قبلاً استفاده شده است.',
                'new_password.required'     => 'در حالت بازنویسی، رمز عبور جدید الزامی است.',
                'new_password.min'          => 'رمز عبور جدید حداقل ۶ کاراکتر باشد.',
                'new_password.max'          => 'رمز عبور جدید حداکثر ۷۲ کاراکتر باشد.',
                'new_password.confirmed'    => 'تکرار رمز عبور جدید مطابقت ندارد.',
            ],
        );

        if (! $force && ! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'رمز عبور فعلی صحیح نیست.',
            ]);
        }

        $newUsername = trim($data['username']);
        $newPassword = $data['new_password'] ?? null;

        if (! $force && $newUsername === $user->username && ! $newPassword) {
            return response()->json([
                'ok'      => false,
                'message' => 'تغییری برای ذخیره وجود ندارد.',
            ], 422);
        }

        $user->username = $newUsername;
        if ($newPassword) {
            $user->password = Hash::make($newPassword);
        }
        $user->save();

        if ($newPassword) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return response()->json([
            'ok'               => true,
            'message'          => 'اطلاعات ورود با موفقیت ذخیره شد.',
            'username'         => $user->username,
            'password_changed' => (bool) $newPassword,
        ]);
    }
}