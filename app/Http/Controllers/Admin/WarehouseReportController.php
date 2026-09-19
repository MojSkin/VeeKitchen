<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\WarehouseReportService;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseReportController extends Controller
{
    public function __construct(
        protected WarehouseReportService $reports,
    ) {}

    /**
     * Today's warehouse ledger report, grouped by movement type.
     */
    public function index(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        $report = $this->reports->todayByType($branch);

        return Inertia::render('Admin/WarehouseReport', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'report' => $report,
        ]);
    }
}
