<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceJobRequest;
use App\Models\ServiceJob;
use App\Services\ServiceJobService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceJobController extends Controller
{
    public function __construct(
        protected ServiceJobService $serviceJobService
    ) {}

    public function index(Request $request)
    {
        $query = ServiceJob::with('serviceType', 'customer')
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('ServiceJobs/Index', [
            'jobs'   => $query->paginate(20),
            'status' => $request->input('status'),
        ]);
    }

    public function show(int $id)
    {
        return Inertia::render('ServiceJobs/Show', [
            'job' => $this->serviceJobService->findServiceJob($id),
        ]);
    }

    public function create()
    {
        return Inertia::render('ServiceJobs/Create');
    }

    public function store(ServiceJobRequest $request)
    {
        $job = $this->serviceJobService->createServiceJob($request->validated());
        return redirect()->route('service-jobs.show', $job->id);
    }

    public function edit(int $id)
    {
        return Inertia::render('ServiceJobs/Edit', [
            'job' => $this->serviceJobService->findServiceJob($id),
        ]);
    }

    public function update(int $id, ServiceJobRequest $request)
    {
        $this->serviceJobService->updateServiceJob($id, $request->validated());
        return redirect()->route('service-jobs.show', $id);
    }

    public function destroy(int $id)
    {
        $this->serviceJobService->deleteServiceJob($id);
        return redirect()->route('service-jobs.index');
    }
}