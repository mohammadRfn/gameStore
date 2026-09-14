<?php

namespace Modules\DigitalMenu\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\DigitalMenu\Services\DigitalMenuService;
use RuntimeException;

class PublicMenuController extends Controller
{
    public function __construct(protected DigitalMenuService $digitalMenuService) {}

    public function entry()
    {
        return Inertia::render('PublicMenu/Entry');
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => 'required|digits:4']);

        try {
            $session = $this->digitalMenuService->consumeCode($data['code']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('menu.session', $session->session_token);
    }

    // برای لینک/QR که کد داخلش embed شده
    public function show(string $code)
    {
        try {
            $session = $this->digitalMenuService->consumeCode($code);
        } catch (RuntimeException $e) {
            return Inertia::render('PublicMenu/Entry', ['error' => $e->getMessage()]);
        }

        return redirect()->route('menu.session', $session->session_token);
    }

    public function session(string $token)
    {
        try {
            $session = $this->digitalMenuService->findActiveByToken($token);
        } catch (RuntimeException $e) {
            return Inertia::render('PublicMenu/Expired', ['message' => $e->getMessage()]);
        }

        return Inertia::render('PublicMenu/Menu', [
            'token'      => $token,
            'items'      => $this->digitalMenuService->getMenuItems($session),
            'selections' => $session->selections->map(fn ($s) => ['item_id' => $s->item_id, 'quantity' => $s->quantity]),
        ]);
    }

    public function select(Request $request, string $token)
    {
        $data = $request->validate(['item_id' => 'required|integer', 'quantity' => 'nullable|integer|min:1']);

        try {
            $this->digitalMenuService->addSelection($token, $data['item_id'], $data['quantity'] ?? 1);
        } catch (RuntimeException $e) {
            return back()->withErrors(['item_id' => $e->getMessage()]);
        }

        return back();
    }

    public function decrease(string $token, int $itemId)
    {
        $this->digitalMenuService->decrementSelection($token, $itemId);
        return back();
    }

    public function submit(string $token)
    {
        try {
            $this->digitalMenuService->submit($token);
        } catch (RuntimeException $e) {
            return back()->withErrors(['submit' => $e->getMessage()]);
        }

        return Inertia::render('PublicMenu/Done');
    }
}