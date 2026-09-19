<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\WarehouseReportExportService;
use App\Services\WarehouseReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarehouseReportController extends Controller
{
    /** @var int Hard cap so a custom range can never drag the whole ledger into memory. */
    protected const MAX_RANGE_DAYS = 92;

    public function __construct(
        protected WarehouseReportService $reports,
        protected WarehouseReportExportService $exports,
    ) {}

    /**
     * Warehouse ledger report grouped by movement type, over a date range.
     *
     * Range presets: today (default), last7, week (current week, Saturday
     * start), month (current month), custom (from/to, Y-m-d, capped).
     */
    public function index(Request $request): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        [$from, $to, $range] = $this->resolveRange($request);

        $report = $this->reports->rangeByType($branch, $from, $to);

        return Inertia::render('Admin/WarehouseReport', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'range' => $range,
            'report' => $report,
        ]);
    }

    /**
     * File exports of the same report: `format=xlsx` streams an Excel
     * workbook, `format=print` returns a self-contained print page.
     */
    public function export(Request $request): StreamedResponse
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        [$from, $to] = $this->resolveRange($request);

        if ($request->query('format') === 'print') {
            return response()->streamDownload(
                fn () => print $this->exports->printView($branch, $from, $to),
                'warehouse-report.html',
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        $filename = $this->exports->filename($branch, $from, $to);

        return response()->streamDownload(
            fn () => print $this->exports->xlsx($branch, $from, $to),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Turn the requested preset into concrete day boundaries.
     *
     * @return array{0: Carbon, 1: Carbon, 2: array{preset: string, from: string, to: string, label: string}}
     */
    protected function resolveRange(Request $request): array
    {
        $preset = $request->query('range', 'today');
        $today = now()->startOfDay();

        switch ($preset) {
            case 'last7':
                $from = $today->copy()->subDays(6);
                $to = now();
                $label = '۷ روز گذشته';
                break;

            case 'week':
                // Iranian week starts on Saturday (Jalali standard).
                $from = $today->copy()->startOfWeek(Carbon::SATURDAY);
                $to = now();
                $label = 'این هفته';
                break;

            case 'month':
                $from = $today->copy()->startOfMonth();
                $to = now();
                $label = 'این ماه';
                break;

            case 'custom':
                $from = $this->parseDate($request->query('from'));
                $to = $this->parseDate($request->query('to'), $today);

                if ($from === null || $to === null) {
                    // Missing/garbled dates fall back to today rather than erroring.
                    return [$today->copy(), now(), $this->rangeMeta('today', $today, now(), 'امروز')];
                }

                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from]; // Tolerate a swapped range.
                }

                if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
                    $to = $from->copy()->addDays(self::MAX_RANGE_DAYS)->endOfDay();
                }

                return [$from->copy(), $to->copy(), $this->rangeMeta('custom', $from, $to, 'بازهٔ دلخواه')];

            default:
                $preset = 'today';
                $from = $today->copy();
                $to = now();
                $label = 'امروز';
                break;
        }

        return [$from->copy(), $to->copy(), $this->rangeMeta($preset, $from, $to, $label)];
    }

    /**
     * @return array{preset: string, from: string, to: string, label: string}
     */
    protected function rangeMeta(string $preset, Carbon $from, Carbon $to, string $label): array
    {
        return [
            'preset' => $preset,
            'from' => $from->copy()->startOfDay()->toIso8601String(),
            'to' => $to->copy()->endOfDay()->toIso8601String(),
            'label' => $label,
        ];
    }

    /**
     * Parse a Y-m-d query param into a Carbon day, or null when unusable.
     */
    protected function parseDate(?string $value, ?Carbon $fallback = null): ?Carbon
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        // createFromFormat tolerates trailing garbage; make sure it doesn't.
        if ($date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
