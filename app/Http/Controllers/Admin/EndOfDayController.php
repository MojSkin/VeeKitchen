<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\EndOfDayService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The end-of-day pipeline: every shift touching today with its money
 * numbers, plus the day's order KPIs — the settlement review page.
 */
class EndOfDayController extends Controller
{
    public function __construct(
        protected EndOfDayService $endOfDay,
    ) {}

    public function index(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        return Inertia::render('Admin/EndOfDay', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'pipeline' => $this->endOfDay->pipeline($branch),
        ]);
    }
}
