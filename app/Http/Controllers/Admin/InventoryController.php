<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MeasurementUnit;
use App\Events\StockChanged;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeVersion;
use App\Models\StockMovement;
use App\Services\CostCalculator;
use App\Services\InventoryService;
use App\Services\RecipeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
        protected RecipeVersionService $recipeVersions,
    ) {}

    /**
     * The warehouse workbench: live material board + product recipes.
     */
    public function index(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        $items = InventoryItem::query()
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit->value,
                'unit_label' => $item->unit->label(),
                'unit_cost' => $item->unit_cost,
                'current_stock' => (float) $item->current_stock,
                'low_stock_threshold' => (float) $item->low_stock_threshold,
                'is_low' => $item->isLowStock(),
                'is_active' => $item->is_active,
                'qr_label' => $item->qr_label,
            ]);

        $calculator = app(CostCalculator::class);

        $products = Product::query()
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->with(['recipes.inventoryItem', 'costComponents'])
            ->get()
            ->map(fn (Product $product) => array_merge([
                'id' => $product->id,
                'name' => $product->name,
                'sale_price' => $product->price,
                'recipes' => $product->recipes->map(fn (ProductRecipe $recipe) => [
                    'inventory_item_id' => $recipe->inventory_item_id,
                    'name' => $recipe->inventoryItem->name,
                    'unit_label' => $recipe->inventoryItem->unit->label(),
                    'unit_cost' => $recipe->inventoryItem->unit_cost,
                    'quantity_per_unit' => (float) $recipe->quantity_per_unit,
                ])->values(),
            ], $this->costSummary($calculator, $product)));

        return Inertia::render('Admin/Inventory', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'items' => $items,
            'products' => $products,
            'units' => collect(MeasurementUnit::cases())
                ->map(fn (MeasurementUnit $unit) => ['value' => $unit->value, 'label' => $unit->label()]),
        ]);
    }

    /**
     * The pricing block shared by the board and the recipe editor.
     *
     * @return array<string, mixed>
     */
    protected function costSummary(CostCalculator $calculator, Product $product): array
    {
        $cost = $calculator->forProduct($product);

        return [
            'material_cost' => $cost['material_cost']->toman,
            'cost_price' => $cost['cost_price']->toman,
            'suggested_sale_price' => $cost['suggested_sale_price']->toman,
            'components' => collect($cost['components'])->map(fn (array $component) => [
                'label' => $component['label'],
                'type' => $component['type'],
                'value' => $component['value'],
                'amount' => $component['amount']->toman,
                'running' => $component['running']->toman,
            ])->values(),
        ];
    }

    /**
     * Manual stock correction (delivery count-up, recount, ...).
     * Positive values add stock, negative values remove it; every
     * correction lands in the ledger.
     */
    public function adjustStock(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $delta = (float) $validated['delta'];
        $newStock = (float) $item->current_stock + $delta;

        if ($newStock < 0) {
            throw ValidationException::withMessages([
                'delta' => 'موجودی نمی‌تواند منفی شود.',
            ]);
        }

        $item->current_stock = $newStock;
        $item->save();

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'type' => 'adjustment',
            'quantity' => $delta,
            'user_id' => $request->user()->id,
            'reason' => $validated['reason'],
        ]);

        StockChanged::dispatch($item->refresh());

        return back()->with('success', "موجودی «{$item->name}» به‌روزرسانی شد.");
    }

    /**
     * Create a new warehouse material.
     */
    public function storeItem(Request $request): RedirectResponse
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        $item = $branch->inventoryItems()->create($validated);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'type' => 'adjustment',
            'quantity' => (float) $validated['current_stock'],
            'user_id' => $request->user()->id,
            'reason' => 'ثبت اولیهٔ متریال',
        ]);

        StockChanged::dispatch($item);

        return back()->with('success', "متریال «{$item->name}» به انبار اضافه شد.");
    }

    /**
     * Replace a product's whole recipe with the submitted lines.
     */
    public function saveRecipe(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['present', 'array'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'lines.*.quantity_per_unit' => ['required', 'numeric', 'gt:0'],
        ]);

        $lines = collect($validated['lines']);

        if ($lines->pluck('inventory_item_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'هر متریال فقط یک‌بار در فرمول می‌آید.',
            ]);
        }

        DB::transaction(function () use ($product, $lines, $request): void {
            $product->recipes()->delete();

            foreach ($lines as $line) {
                ProductRecipe::create([
                    'product_id' => $product->id,
                    'inventory_item_id' => (int) $line['inventory_item_id'],
                    'quantity_per_unit' => (float) $line['quantity_per_unit'],
                ]);
            }

            // History: freeze the new state (lines + unit costs + cost chain)
            // as the next sequential version of this product's recipe.
            $this->recipeVersions->record($product, $request->user());
        });

        return back()->with('success', "فرمول «{$product->name}» ذخیره شد.");
    }

    /**
     * A product's recipe version history.
     */
    public function recipeVersions(Product $product): Response
    {
        $product->loadMissing('recipes.inventoryItem');

        $versions = $product->versions()
            ->latest('version_number')
            ->get()
            ->map(fn (ProductRecipeVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'lines' => $version->lines,
                'components' => $version->components ?? [],
                'material_cost' => $version->material_cost,
                'cost_price' => $version->cost_price,
                'suggested_sale_price' => $version->suggested_sale_price,
                'saved_at' => $version->created_at?->toISOString(),
                'author' => $version->author?->name,
            ]);

        return Inertia::render('Admin/RecipeVersions', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sale_price' => $product->price,
            ],
            'versions' => $versions,
        ]);
    }

    /**
     * Toggle a material's availability for ordering/production.
     */
    public function toggleItem(InventoryItem $item): RedirectResponse
    {
        $item->is_active = ! $item->is_active;
        $item->save();

        $label = $item->is_active ? 'فعال' : 'غیرفعال';

        return back()->with('success', "متریال «{$item->name}» {$label} شد.");
    }

    /**
     * Record waste for a material: stock drops, ledger + waste log fill.
     */
    public function storeWaste(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'expired_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $this->inventory->logWaste(
            $item,
            (float) $validated['quantity'],
            $validated['reason'],
            $request->user(),
            $validated['expired_on'] ?? null,
        );

        return back()->with('success', "ضایعات «{$item->name}» ثبت شد.");
    }
}
