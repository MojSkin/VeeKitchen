<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TableController extends Controller
{
    /**
     * The live table grid.
     */
    public function index(): Response
    {
        $tables = RestaurantTable::query()
            ->with('openOrders')
            ->orderBy('label')
            ->get();

        return Inertia::render('Admin/Tables', [
            'tables' => $tables->map(fn (RestaurantTable $table) => [
                'id' => $table->id,
                'label' => $table->label,
                'capacity' => $table->capacity,
                'status' => $table->status->value,
                'status_label' => $table->status->label(),
                'qr_token' => $table->qr_token,
                'open_orders' => $table->openOrders->count(),
            ]),
        ]);
    }

    /**
     * A printable QR page for the table's reservation URL (SVG, no deps).
     */
    public function qr(RestaurantTable $table): Response
    {
        $url = route('table.reserve', ['qrToken' => $table->qr_token]);

        return Inertia::render('Admin/TableQr', [
            'table' => [
                'id' => $table->id,
                'label' => $table->label,
            ],
            'url' => $url,
        ]);
    }

    /**
     * Rotate a table's token (old QR codes stop working).
     */
    public function rotateToken(RestaurantTable $table): RedirectResponse
    {
        $table->update([
            'qr_token' => RestaurantTable::generateQrToken(),
        ]);

        return back()->with('success', "توکن QR میز {$table->label} تازه‌سازی شد؛ کدهای چاپ‌شده قبلی باطل شدند.");
    }
}
