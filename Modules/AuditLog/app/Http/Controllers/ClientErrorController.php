<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Facades\Audit;

// دریافت خطاهای فرانت (Vue/JS) و ثبت آن‌ها در کانال error
class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'message'   => ['required', 'string', 'max:1000'],
            'source'    => ['nullable', 'string', 'max:32'],
            'stack'     => ['nullable', 'string', 'max:6000'],
            'url'       => ['nullable', 'string', 'max:500'],
            'component' => ['nullable', 'string', 'max:191'],
            'info'      => ['nullable', 'string', 'max:191'],
            'line'      => ['nullable', 'integer'],
            'column'    => ['nullable', 'integer'],
        ]);

        // فقط مسیر صفحه؛ query string ممکن است داده‌ی حساس داشته باشد
        $path = isset($data['url']) ? (string) parse_url($data['url'], PHP_URL_PATH) : null;
        $lines = (int) config('auditlog.capture.error.trace_lines', 20);

        Audit::log(
            LogChannel::Error,
            'error.frontend',
            $data['message'],
            LogLevel::Error,
            null,
            null,
            null,
            [
                'source'     => $data['source'] ?? null,
                'path'       => $path ?: null,
                'component'  => $data['component'] ?? null,
                'info'       => $data['info'] ?? null,
                'line'       => $data['line'] ?? null,
                'column'     => $data['column'] ?? null,
                'trace'      => array_slice(preg_split('/\R/', (string) ($data['stack'] ?? '')) ?: [], 0, $lines),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 191),
            ],
            ['frontend'],
        );

        return response()->noContent();
    }
}