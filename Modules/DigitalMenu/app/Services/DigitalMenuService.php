<?php

namespace Modules\DigitalMenu\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\DigitalMenu\Models\DigitalMenuSelection;
use Modules\DigitalMenu\Models\DigitalMenuSession;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Services\OrderItemService;
use Modules\Stock\Models\Item;
use RuntimeException;

class DigitalMenuService
{
    protected OrderItemService $orderItemService;

    public function __construct(OrderItemService $orderItemService)
    {
        $this->orderItemService = $orderItemService;
    }

    /**
     * برای یک فاکتور، منوی دیجیتال با کد ۴ رقمی یکبارمصرف فعال می‌کند.
     * سشن‌های قبلیِ همان فاکتور که هنوز مصرف/ثبت نشده بودند منقضی می‌شوند.
     */
    public function activateForInvoice(int $invoiceId, array $categoryIds): DigitalMenuSession
    {
        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->isLocked()) {
            throw new RuntimeException('برای فاکتور پرداخت‌شده یا مرجوع‌شده نمی‌توان منوی دیجیتال فعال کرد.');
        }
        if (empty($categoryIds)) {
            throw new RuntimeException('حداقل یک دسته‌بندی باید انتخاب شود.');
        }

        return DB::transaction(function () use ($invoiceId, $categoryIds) {
            DigitalMenuSession::where('invoice_id', $invoiceId)
                ->whereIn('status', [DigitalMenuSession::STATUS_PENDING, DigitalMenuSession::STATUS_ACTIVE])
                ->update(['status' => DigitalMenuSession::STATUS_EXPIRED]);

            return DigitalMenuSession::create([
                'invoice_id'   => $invoiceId,
                'code'         => $this->generateUniqueCode(),
                'category_ids' => array_values($categoryIds),
                'status'       => DigitalMenuSession::STATUS_PENDING,
                'expires_at'   => now()->addHours(6),
            ]);
        });
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = DigitalMenuSession::where('code', $code)
                ->whereIn('status', [DigitalMenuSession::STATUS_PENDING, DigitalMenuSession::STATUS_ACTIVE])
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        } while ($exists);

        return $code;
    }

    /** مصرف کد: فقط یک‌بار قابل استفاده؛ بعد از آن با session_token ادامه پیدا می‌کند. */
    public function consumeCode(string $code): DigitalMenuSession
    {
        return DB::transaction(function () use ($code) {
            $session = DigitalMenuSession::where('code', $code)
                ->where('status', DigitalMenuSession::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (!$session || $session->isExpired()) {
                throw new RuntimeException('کد وارد شده معتبر نیست یا قبلاً استفاده شده است.');
            }

            $session->update([
                'status'        => DigitalMenuSession::STATUS_ACTIVE,
                'session_token' => Str::random(48),
                'entered_at'    => now(),
                'expires_at'    => now()->addHours(2),
            ]);

            return $session->fresh();
        });
    }

    public function findActiveByToken(string $token): DigitalMenuSession
    {
        $session = DigitalMenuSession::where('session_token', $token)
            ->where('status', DigitalMenuSession::STATUS_ACTIVE)
            ->with('selections.item')
            ->first();

        if (!$session || $session->isExpired()) {
            throw new RuntimeException('این جلسه منقضی شده است.');
        }

        return $session;
    }

    /** فقط اطلاعات امن برای نمایش به مشتری — بدون موجودی/جزئیات حساس. */
    public function getMenuItems(DigitalMenuSession $session): Collection
    {
        return Item::whereIn('category_id', $session->category_ids)
            ->select('id', 'name', 'sale_price', 'category_id', 'image_path')
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'id'         => $item->id,
                'name'       => $item->name,
                'sale_price' => $item->sale_price,
                'image_url'  => $item->image_path ? $this->buildAssetUrl($item->image_path) : null,
            ]);
    }

    /**
     * برخلاف Storage::url() که از APP_URL (معمولاً localhost) استفاده می‌کند،
     * این متد از همون IP شبکه‌ی محلی که برای لینک منو تشخیص می‌دیم استفاده می‌کند
     * تا عکس هم از دستگاه مشتری قابل‌بارگذاری باشه.
     */
    protected function buildAssetUrl(string $relativePath): string
    {
        $base = config('app.digital_menu_base_url') ?: $this->detectLanBaseUrl();
        return rtrim($base, '/') . '/storage/' . ltrim($relativePath, '/');
    }

    public function addSelection(string $token, int $itemId, int $quantity = 1): DigitalMenuSession
    {
        $session = $this->findActiveByToken($token);
        $item = Item::whereIn('category_id', $session->category_ids)->findOrFail($itemId);

        $existing = DigitalMenuSelection::where('digital_menu_session_id', $session->id)
            ->where('item_id', $item->id)->first();

        if ($existing) {
            $existing->update(['quantity' => $existing->quantity + $quantity]);
        } else {
            DigitalMenuSelection::create([
                'digital_menu_session_id' => $session->id,
                'item_id'                 => $item->id,
                'quantity'                => max(1, $quantity),
            ]);
        }

        return $session->fresh('selections.item');
    }

    /** کاهش تعداد یک واحد؛ اگر به صفر برسد کل ردیف انتخاب حذف می‌شود. */
    public function decrementSelection(string $token, int $itemId): DigitalMenuSession
    {
        $session = $this->findActiveByToken($token);
        $selection = DigitalMenuSelection::where('digital_menu_session_id', $session->id)
            ->where('item_id', $itemId)->first();

        if ($selection) {
            $selection->quantity <= 1 ? $selection->delete() : $selection->decrement('quantity');
        }

        return $session->fresh('selections.item');
    }

    /** تبدیل انتخاب‌های نهایی‌شده به order_items واقعی روی فاکتور. */
    public function submit(string $token): DigitalMenuSession
    {
        $session = $this->findActiveByToken($token);

        if ($session->selections->isEmpty()) {
            throw new RuntimeException('هیچ آیتمی انتخاب نشده است.');
        }

        return DB::transaction(function () use ($session) {
            foreach ($session->selections as $selection) {
                $this->orderItemService->createOrderItem([
                    'item_id'  => $selection->item_id,
                    'quantity' => $selection->quantity,
                ], $session->invoice_id);
            }

            $session->update(['status' => DigitalMenuSession::STATUS_SUBMITTED, 'submitted_at' => now()]);

            return $session->fresh('selections.item');
        });
    }

    protected const VALID_STATUSES = [
        DigitalMenuSession::STATUS_PENDING,
        DigitalMenuSession::STATUS_ACTIVE,
        DigitalMenuSession::STATUS_SUBMITTED,
        DigitalMenuSession::STATUS_EXPIRED,
    ];

    public function statusForInvoice(int $invoiceId): ?DigitalMenuSession
    {
        return DigitalMenuSession::where('invoice_id', $invoiceId)
            ->whereIn('status', self::VALID_STATUSES)
            ->latest('id')
            ->first();
    }

    public function buildLink(string $code): string
    {
        $base = config('app.digital_menu_base_url') ?: $this->detectLanBaseUrl();
        return rtrim($base, '/') . '/menu/' . $code;
    }

    protected function detectLanBaseUrl(): string
    {
        $port = request()->getPort() ?: 80;
        $ip   = $this->detectLanIp() ?? request()->getHost();

        return "http://{$ip}:{$port}";
    }

    protected function detectLanIp(): ?string
    {
        $viaSocket = null;

        if (function_exists('socket_create')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock !== false) {
                $ip = null;
                $connected = @socket_connect($sock, '8.8.8.8', 53);
                if ($connected) {
                    socket_getsockname($sock, $ip);
                }
                socket_close($sock);
                $viaSocket = $ip;
                if ($ip && $this->isPrivateLanIp($ip)) {
                    \Log::info('digital-menu: IP از طریق سوکت پیدا شد', ['ip' => $ip]);
                    return $ip;
                }
            } else {
                \Log::info('digital-menu: socket_create شکست خورد یا در دسترس نیست');
            }
        } else {
            \Log::info('digital-menu: اکستنشن sockets فعال نیست');
        }

        $viaCommand = $this->detectLanIpFromSystemCommand();

        \Log::info('digital-menu: نتیجه‌ی تشخیص IP', [
            'via_socket'  => $viaSocket,
            'via_command' => $viaCommand,
            'shell_exec_enabled' => function_exists('shell_exec'),
            'disable_functions'  => ini_get('disable_functions'),
        ]);

        return $viaCommand;
    }

    protected function detectLanIpFromSystemCommand(): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $output = PHP_OS_FAMILY === 'Windows'
            ? @shell_exec('ipconfig')
            : @shell_exec('hostname -I 2>/dev/null || ifconfig 2>/dev/null');

        if (!$output) {
            return null;
        }

        $ip = PHP_OS_FAMILY === 'Windows'
            ? $this->extractBestWindowsIp($output)
            : $this->extractBestUnixIp($output);

        \Log::info('digital-menu: خروجی دستور سیستم', [
            'chosen_ip' => $ip,
            'output'    => mb_substr($output, 0, 2000),
        ]);

        return $ip;
    }

    /**
     * خروجی ipconfig را بلوک‌به‌بلوک (به ازای هر آداپتور) می‌خواند تا آداپتورهای
     * مجازی/VPN/تونل را کنار بگذارد و آداپتور واقعی شبکه (با Default Gateway واقعی) را انتخاب کند.
     */
    protected function extractBestWindowsIp(string $output): ?string
    {
        $ignoreKeywords = ['tun', 'tap', 'vpn', 'virtual', 'loopback', 'hyper-v', 'vmware',
            'outline', 'singbox', 'wireguard', 'ppp', 'teredo', 'bluetooth'];

        $lines = preg_split('/\r?\n/', $output);
        $candidates = [];
        $current = null;
        $sawGatewayLabel = false;

        $flush = function () use (&$candidates, &$current) {
            if ($current && !$current['ignored'] && !$current['disconnected']
                && $current['ip'] && $this->isPrivateLanIp($current['ip'])) {
                $candidates[] = ['ip' => $current['ip'], 'has_gateway' => $current['gateway']];
            }
        };

        foreach ($lines as $line) {
            if (preg_match('/^(.*adapter[^:]*):\s*$/i', trim($line), $m)) {
                $flush();
                $name = mb_strtolower($m[1]);
                $ignored = false;
                foreach ($ignoreKeywords as $kw) {
                    if (str_contains($name, $kw)) {
                        $ignored = true;
                        break;
                    }
                }
                $current = ['ignored' => $ignored, 'ip' => null, 'gateway' => false, 'disconnected' => false];
                $sawGatewayLabel = false;
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (stripos($line, 'Media disconnected') !== false) {
                $current['disconnected'] = true;
            }
            if (preg_match('/IPv4[^:]*:\s*((?:\d{1,3}\.){3}\d{1,3})/i', $line, $ipMatch)) {
                $current['ip'] = $ipMatch[1];
            }
            if (stripos($line, 'Default Gateway') !== false) {
                $sawGatewayLabel = true;
            }
            // آدرس گیت‌وی گاهی روی همون خط، گاهی روی خط بعدی (به تنهایی) میاد
            if ($sawGatewayLabel && preg_match('/^\s*((?:\d{1,3}\.){3}\d{1,3})\s*$/', $line, $gwMatch)) {
                $current['gateway'] = true;
            } elseif (preg_match('/Default Gateway[^:]*:\s*((?:\d{1,3}\.){3}\d{1,3})/i', $line)) {
                $current['gateway'] = true;
            }
        }
        $flush();

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['has_gateway'] <=> $a['has_gateway']);

        return $candidates[0]['ip'];
    }

    protected function extractBestUnixIp(string $output): ?string
    {
        if (preg_match_all('/inet\s+((?:\d{1,3}\.){3}\d{1,3})/i', $output, $matches)) {
            foreach ($matches[1] as $ip) {
                if ($this->isPrivateLanIp($ip)) {
                    return $ip;
                }
            }
        }
        return null;
    }

    protected function isPrivateLanIp(string $ip): bool
    {
        return (bool) preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $ip);
    }
}