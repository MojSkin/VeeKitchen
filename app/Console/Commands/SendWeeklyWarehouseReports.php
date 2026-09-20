<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\WarehouseWeeklyReport;
use App\Models\Branch;
use App\Services\WarehouseReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every active branch's admins their warehouse turnover for the
 * seven completed days before the run day.
 *
 * Scheduled Saturday 07:00 so the window lands on the Iranian week
 * (Saturday → Friday). The window never includes "today" — a weekly
 * report sent in the morning must not claim a half-finished day.
 */
class SendWeeklyWarehouseReports extends Command
{
    protected $signature = 'reports:weekly-warehouse
        {--from= : Override the window start (Y-m-d, inclusive)}
        {--to= : Override the window end (Y-m-d, inclusive)}';

    protected $description = 'Emails the weekly warehouse report to each active branch\'s admins';

    public function handle(WarehouseReportService $reports): int
    {
        [$from, $to] = $this->resolveWindow();

        $sent = 0;
        $skipped = 0;

        Branch::query()->where('is_active', true)->each(function (Branch $branch) use ($reports, $from, $to, &$sent, &$skipped): void {
            $report = $reports->rangeByType($branch, $from, $to);

            if ($report['movement_count'] === 0) {
                $skipped++;

                $this->line("{$branch->name}: no movements — skipped");

                return;
            }

            $admins = $branch->staff->where('role', UserRole::Admin);

            if ($admins->isEmpty()) {
                $skipped++;

                $this->warn("{$branch->name}: no admins — skipped");

                return;
            }

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new WarehouseWeeklyReport($branch, $report, $from, $to));
                $sent++;
            }

            $this->info("{$branch->name}: report sent to {$admins->count()} admin(s)");
        });

        $this->info("Done — {$sent} email(s) sent, {$skipped} branch window(s) skipped.");

        return self::SUCCESS;
    }

    /**
     * The seven completed days before the run day, unless overridden.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveWindow(): array
    {
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if ($fromOption !== null && $toOption !== null) {
            $from = Carbon::parse($fromOption)->startOfDay();
            $to = Carbon::parse($toOption)->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to];
        }

        $to = now()->subDay()->endOfDay();
        $from = now()->subDays(7)->startOfDay();

        return [$from, $to];
    }
}
