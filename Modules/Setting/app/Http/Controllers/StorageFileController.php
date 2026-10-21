<?php

declare(strict_types=1);

namespace Modules\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StorageFileController extends Controller
{
    /**
     * سرو فایل‌های دیسک public بدون نیاز به symlink (برای بیلد NativePHP).
     */
    public function show(string $path): Response
    {
        try {
            $disk = Storage::disk('public');

            abort_unless($disk->exists($path), 404);

            return $disk->response($path, null, [
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (Throwable $e) {
            // مسیرهای مخرب (مثل ..) یا هر خطای خواندن
            abort(404);
        }
    }
}