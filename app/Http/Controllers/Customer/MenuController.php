<?php

namespace App\Http\Controllers\Customer;

use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\MenuDiscountPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * The public menu link (no table attached).
     */
    public function publicMenu(): Response
    {
        return $this->menuResponse(null);
    }

    /**
     * A customer just scanned the table QR — reserve the table and open its menu.
     */
    public function reserveTable(Request $request, string $qrToken): Response
    {
        $table = RestaurantTable::query()->where('qr_token', $qrToken)->first();

        abort_if($table === null, 404, 'کد QR معتبر نیست.');

        if ($table->status === TableStatus::Free) {
            $table->update([
                'status' => TableStatus::Reserved,
                'occupied_at' => now(),
            ]);
        }

        return $this->menuResponse($table);
    }

    /**
     * The menu for a specific table (guest already seated).
     */
    public function tableMenu(Request $request, string $qrToken): Response
    {
        $table = RestaurantTable::query()->where('qr_token', $qrToken)->first();

        abort_if($table === null, 404, 'کد QR معتبر نیست.');

        return $this->menuResponse($table);
    }

    /**
     * Shared menu renderer.
     */
    protected function menuResponse(?RestaurantTable $table): Response
    {
        $branchId = $table?->branch_id ?? $this->firstBranchId();

        $categories = MenuCategory::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->with(['products' => fn ($query) => $query->available()->orderBy('position')])
            ->orderBy('position')
            ->get()
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'products' => $category->products->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'image_path' => $product->image_path,
                    'is_available' => $product->is_available,
                ]),
            ]);

        $presenter = new MenuDiscountPresenter;
        $productIds = $categories->pluck('products')->flatten(1)->pluck('id')->all();

        return Inertia::render('Customer/Menu', [
            'table' => $table ? [
                'id' => $table->id,
                'label' => $table->label,
                'qr_token' => $table->qr_token,
                'status' => $table->status->value,
            ] : null,
            'categories' => $categories,
            'discounts' => [
                'banner' => $presenter->activeForMenu($branchId),
                'product_badges' => $presenter->bestBadgesByProduct($branchId, $productIds),
            ],
        ]);
    }

    /**
     * The public menu falls back to the first active branch.
     */
    protected function firstBranchId(): int
    {
        $branch = Branch::query()->where('is_active', true)->first();

        abort_if($branch === null, 503, 'هیچ شعبه فعالی ثبت نشده است.');

        return $branch->id;
    }
}
