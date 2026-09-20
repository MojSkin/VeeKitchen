<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\DashboardExportService;
use App\Services\DashboardSnapshotService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardSnapshotService $snapshots,
        protected DashboardExportService $exports,
    ) {}

    /**
     * The admin dashboard: sales trend, today's KPIs, low-stock board,
     * and a live snapshot of the dining room.
     */
    public function index(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        return Inertia::render('Admin/Dashboard', [
            ...$this->snapshots->snapshot($branch),
        ]);
    }

    /**
     * Download the dashboard as an Excel workbook (chart + KPI sheets).
     */
    public function export(): StreamedResponse
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        return response()->streamDownload(
            fn () => print ($this->exports->xlsx($branch)),
            'dashboard-'.now()->format('Y-m-d').'.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
