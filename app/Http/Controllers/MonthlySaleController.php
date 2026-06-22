<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonthlySaleRequest;
use App\Services\MonthlySaleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonthlySaleController extends Controller
{
    public function __construct(
        protected MonthlySaleService $monthlySaleService
    ) {}

    public function index(Request $request)
    {
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);

        return Inertia::render('MonthlySales/Index', [
            'sales' => $this->monthlySaleService->getMonthlySales($year, $month),
            'year'  => $year,
            'month' => $month,
        ]);
    }

    public function store(MonthlySaleRequest $request)
    {
        $data = $request->validated();

        $this->monthlySaleService->createOrUpdateMonthlySales(
            $data['year'],
            $data['month'],
            $data
        );

        return redirect()->back();
    }
}