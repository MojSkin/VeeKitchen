<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    /**
     * The discounts board: every branch discount with its live usage meter.
     */
    public function index(): Response
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        $discounts = Discount::query()
            ->where('branch_id', $branch->id)
            ->with(['category:id,name', 'product:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Discount $discount) => $this->present($discount));

        return Inertia::render('Admin/Discounts', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'discounts' => $discounts,
            'categories' => MenuCategory::query()
                ->where('branch_id', $branch->id)
                ->orderBy('position')
                ->get(['id', 'name'])
                ->map(fn (MenuCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ]),
            'products' => Product::query()
                ->where('branch_id', $branch->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                ]),
            'types' => collect(DiscountType::cases())
                ->map(fn (DiscountType $type) => ['value' => $type->value, 'label' => $type->label()]),
            'scopes' => collect(DiscountScope::cases())
                ->map(fn (DiscountScope $scope) => ['value' => $scope->value, 'label' => $scope->label()]),
        ]);
    }

    /**
     * Create a discount from the board's form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateDiscount($request);

        $this->assertTargetPresent($validated);

        Discount::create($this->attributesFrom($validated, $request));

        return back()->with('success', 'تخفیف «'.$validated['name'].'» ساخته شد.');
    }

    /**
     * Update an editable discount.
     */
    public function update(Request $request, Discount $discount): RedirectResponse
    {
        $this->assertEditable($discount);

        $validated = $this->validateDiscount($request);

        $this->assertTargetPresent($validated);

        $discount->update($this->attributesFrom($validated, $request));

        return back()->with('success', 'تخفیف «'.$discount->name.'» به‌روزرسانی شد.');
    }

    /**
     * Activate or pause a discount.
     */
    public function toggle(Request $request, Discount $discount): RedirectResponse
    {
        $discount->update(['is_active' => ! $discount->is_active]);

        return back()->with('success', $discount->is_active
            ? 'تخفیف «'.$discount->name.'» فعال شد.'
            : 'تخفیف «'.$discount->name.'» متوقف شد.');
    }

    /**
     * Delete a discount entirely — only allowed while it has no recorded usage.
     */
    public function destroy(Discount $discount): RedirectResponse
    {
        if ($discount->used_count > 0) {
            return back()->with('error', 'تخفیف «'.$discount->name.'» سابقهٔ استفاده دارد و حذف نمی‌شود؛ آن را متوقف کنید.');
        }

        $name = $discount->name;

        $discount->delete();

        return back()->with('success', 'تخفیف «'.$name.'» حذف شد.');
    }

    /**
     * Shared validation for store/update.
     *
     * @return array<string, mixed>
     */
    protected function validateDiscount(Request $request): array
    {
        // Uppercase before validation so the unique check is case-insensitive
        // regardless of the database's collation.
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('discounts', 'code')->ignore($request->route('discount'))],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'integer', 'min:0'],
            'applies_to' => ['required', Rule::enum(DiscountScope::class)],
            'menu_category_id' => ['nullable', 'integer', 'exists:menu_categories,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'min_order_total' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * Scope targets must match the chosen scope — mirror of the enum's rule.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function assertTargetPresent(array $validated): void
    {
        $scope = DiscountScope::from($validated['applies_to']);

        if ($scope === DiscountScope::EntireOrder) {
            return;
        }

        $column = $scope->targetColumn();

        if (empty($validated[$column])) {
            throw ValidationException::withMessages([
                $column => 'برای تخفیف روی '.$scope->label().'، مقصد را انتخاب کنید.',
            ]);
        }
    }

    /**
     * Only rows without recorded usage may change — usage history must not
     * silently drift from the orders that carry it.
     */
    protected function assertEditable(Discount $discount): void
    {
        if ($discount->used_count > 0) {
            throw ValidationException::withMessages([
                'name' => 'این تخفیف سابقهٔ استفاده دارد و فقط می‌تواند متوقف یا حذف شود.',
            ]);
        }
    }

    /**
     * Model attributes from validated input.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function attributesFrom(array $validated, Request $request): array
    {
        $branchId = Branch::query()->orderBy('id')->value('id');

        return [
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'code' => $validated['code'] !== null && $validated['code'] !== ''
                ? strtoupper($validated['code']) : null,
            'type' => DiscountType::from($validated['type']),
            'value' => (int) $validated['value'],
            'applies_to' => DiscountScope::from($validated['applies_to']),
            'menu_category_id' => $validated['menu_category_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'min_order_total' => (int) ($validated['min_order_total'] ?? 0),
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'usage_limit_total' => $validated['usage_limit_total'] ?? null,
            'usage_limit_per_user' => $validated['usage_limit_per_user'] ?? null,
        ];
    }

    /**
     * The board row shape.
     *
     * @return array<string, mixed>
     */
    protected function present(Discount $discount): array
    {
        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'code' => $discount->code,
            'type' => $discount->type->value,
            'type_label' => $discount->type->label(),
            'value' => $discount->value,
            'applies_to' => $discount->applies_to->value,
            'scope_label' => $discount->applies_to->label(),
            'target_name' => $this->targetName($discount),
            'min_order_total' => $discount->min_order_total,
            'starts_at' => $discount->starts_at?->toIso8601String(),
            'expires_at' => $discount->expires_at?->toIso8601String(),
            'usage_limit_total' => $discount->usage_limit_total,
            'usage_limit_per_user' => $discount->usage_limit_per_user,
            'used_count' => $discount->used_count,
            'is_active' => $discount->is_active,
            'currently_active' => $discount->isActiveAt(),
            'has_total_headroom' => $discount->hasTotalHeadroom(),
            'is_editable' => $discount->used_count === 0,
            'is_deletable' => $discount->used_count === 0,
        ];
    }

    /**
     * Human name of the scope target, or null for entire orders.
     */
    protected function targetName(Discount $discount): ?string
    {
        return match ($discount->applies_to) {
            DiscountScope::EntireOrder => null,
            DiscountScope::Category => $discount->category?->name,
            DiscountScope::Product => $discount->product?->name,
        };
    }
}
