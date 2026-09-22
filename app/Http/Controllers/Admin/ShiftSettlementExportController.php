<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StaffShift;
use App\Services\ShiftSettlementExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File exports of one shift's settlement: `format=print` streams a
 * self-contained print page, anything else an XLSX workbook.
 */
class ShiftSettlementExportController extends Controller
{
    public function __construct(
        protected ShiftSettlementExportService $exports,
    ) {}

    public function show(StaffShift $shift): StreamedResponse
    {
        // Exports read one branch's settlement; scope the lookup to the
        // admin's own branch so a forged id never crosses branches.
        $admin = request()->user();
        $branchId = $admin->branch_id
            ?? Branch::query()->where('is_active', true)->orderBy('id')->value('id');

        abort_unless($shift->branch_id === $branchId, 404);

        if (request()->query('format') === 'print') {
            return response()->streamDownload(
                fn () => print $this->exports->printView($shift),
                'shift-settlement-'.$shift->id.'.html',
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        return response()->streamDownload(
            fn () => print $this->exports->xlsx($shift),
            $this->exports->filename($shift),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
