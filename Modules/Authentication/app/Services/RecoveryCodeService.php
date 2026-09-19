<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class RecoveryCodeService
{
    // بدون حروف/اعداد گیج‌کننده: 0 O 1 I
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 16;

    /** کد جدید می‌سازد، هشش را ذخیره می‌کند و متن اصلی را یک‌بار برمی‌گرداند. */
    public function issueFor(User $user): string
    {
        $code = $this->generate();

        $user->recovery_code_hash = Hash::make($this->normalize($code));
        $user->save();

        return $code;
    }

    /** کاربری که این کد متعلق به اوست (یا null). */
    public function findUser(string $input): ?User
    {
        $normalized = $this->normalize($input);

        if (strlen($normalized) !== self::LENGTH) {
            return null;
        }

        return User::query()
            ->whereNotNull('recovery_code_hash')
            ->get()
            ->first(fn (User $u) => Hash::check($normalized, $u->recovery_code_hash));
    }

    private function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $chars = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return implode('-', str_split($chars, 4)); // XXXX-XXXX-XXXX-XXXX
    }

    private function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}