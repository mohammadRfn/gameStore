<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Authentication\Services\RecoveryCodeService;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Facades\Audit;

/**
 * ساخت حساب مدیر در اولین اجرای برنامه (نصب تازه، جدول users خالی).
 * به‌محض وجود حتی یک کاربر، این صفحه برای همیشه بسته است؛ پس روی نصب‌های فعال هیچ ریسکی ندارد.
 */
class SetupController extends Controller
{
    public function __construct(private readonly RecoveryCodeService $recovery) {}

    public function show(): Response|RedirectResponse
    {
        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        return Inertia::render('Setup', ['step' => 'form', 'recoveryCode' => null]);
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $data = $request->validate(
            [
                'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^\S+$/u'],
                'password' => ['required', 'string', 'min:6', 'max:72', 'confirmed'],
            ],
            [
                'username.required'  => 'نام کاربری را وارد کن.',
                'username.min'       => 'نام کاربری حداقل ۳ کاراکتر باشد.',
                'username.max'       => 'نام کاربری حداکثر ۵۰ کاراکتر باشد.',
                'username.regex'     => 'نام کاربری نباید فاصله داشته باشد.',
                'password.required'  => 'رمز عبور را وارد کن.',
                'password.min'       => 'رمز عبور حداقل ۶ کاراکتر باشد.',
                'password.max'       => 'رمز عبور حداکثر ۷۲ کاراکتر باشد.',
                'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
            ],
        );

        $username = trim($data['username']);

        // بررسی و ساخت در یک تراکنش تا دو درخواست هم‌زمان دو مدیر نسازند
        $user = DB::transaction(function () use ($username, $data): ?User {
            if (User::query()->exists()) {
                return null;
            }

            return User::query()->create([
                'name'     => $username,
                'username' => $username,
                'email'    => $username . '@gameshop.local', // ستون email در جدول users اجباری و یکتاست؛ ورود با username است
                'password' => Hash::make($data['password']),
            ]);
        });

        if ($user === null) {
            throw ValidationException::withMessages(['username' => 'راه‌اندازی اولیه قبلاً انجام شده است.']);
        }

        $code = $this->recovery->issueFor($user);

        Audit::security('setup_owner_created', 'حساب مدیر در راه‌اندازی اولیه ساخته شد', ['user_id' => $user->id], LogLevel::Critical);

        Auth::login($user);
        $request->session()->regenerate();

        return Inertia::render('Setup', ['step' => 'recovery', 'recoveryCode' => $code]);
    }
}