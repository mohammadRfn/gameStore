<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileRequest;
use App\Services\StoreProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class StoreProfileController extends Controller
{
    public function __construct(
        protected StoreProfileService $storeProfileService
    ) {}

    public function index(Request $request)
    {
        $profiles = $this->storeProfileService->getAll();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $profiles,
                'meta' => ['count' => $profiles->count()],
            ]);
        }

        return Inertia::render('StoreProfiles/Index', [
            'profiles' => $profiles,
            'primary'  => $this->storeProfileService->findPrimary(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $profile = $this->storeProfileService->findById($id);

        if ($request->wantsJson()) {
            return response()->json(['data' => $profile]);
        }

        return Inertia::render('StoreProfiles/Show', ['profile' => $profile]);
    }

    public function store(StoreProfileRequest $request)
    {
        try {
            $profile = $this->storeProfileService->create($request->validated());

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'data' => $profile], 201);
            }

            return redirect()->route('store-profiles.index')->with('success', 'پروفایل فروشگاه ثبت شد.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['profile' => $e->getMessage()]);
        }
    }

    public function update(StoreProfileRequest $request, int $id)
    {
        try {
            $profile = $this->storeProfileService->update($id, $request->validated());

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'data' => $profile]);
            }

            return redirect()->back()->with('success', 'پروفایل به‌روزرسانی شد.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['profile' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->storeProfileService->delete($id);

            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->back()->with('success', 'پروفایل حذف شد.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['profile' => $e->getMessage()]);
        }
    }

    public function setPrimary(Request $request, int $id)
    {
        try {
            $profile = $this->storeProfileService->setPrimary($id);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'data' => $profile]);
            }

            return redirect()->back()->with('success', 'پروفایل اصلی تنظیم شد.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['profile' => $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        $term = $request->get('q', '');
        $results = $this->storeProfileService->search($term);

        if ($request->wantsJson()) {
            return response()->json(['data' => $results]);
        }

        return Inertia::render('StoreProfiles/Index', [
            'profiles'   => $results,
            'searchTerm' => $term,
        ]);
    }
}
