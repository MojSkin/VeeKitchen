/**
 * Shared purchase-order form behaviour, used by both the create and the
 * edit page. Lines are plain string-based refs (inputs), while `submit()`
 * casts to numbers exactly like the backend validation expects.
 *
 * @param {{
 *   suppliers: Array<{id: number, name: string}>,
 *   items: Array<{id: number, name: string, unit_label: string, unit_cost: number}>,
 *   initial?: {supplier_id?: number|string, notes?: string, lines?: Array<*>},
 *   submitRouteName: string,
 *   routeParams?: Record<string, unknown>,
 *   spoofMethod?: 'post'|'put',
 *   onSaved?: () => void,
 * }} options
 */
export function usePurchaseOrderForm(options) {
    const { initial } = options;
    const spoofMethod = options.spoofMethod ?? 'post';

    const form = ref({
        supplier_id: initial?.supplier_id ?? '',
        notes: initial?.notes ?? '',
        lines: (initial?.lines?.length ? initial.lines : [{ inventory_item_id: '', quantity: '', unit_cost: '' }]).map(
            (line) => ({
                inventory_item_id: line.inventory_item_id ?? '',
                quantity: line.quantity ?? '',
                unit_cost: line.unit_cost ?? '',
            }),
        ),
    });

    const submitting = ref(false);

    const itemById = computed(() => new Map(options.items.map((item) => [item.id, item])));

    /** A material can appear on only one line — already-used options gray out. */
    function isTaken(line, itemId) {
        return form.value.lines.some(
            (candidate) => candidate !== line && Number(candidate.inventory_item_id) === Number(itemId),
        );
    }

    const hasFreeItems = computed(() =>
        options.items.some(
            (item) => !form.value.lines.some((line) => Number(line.inventory_item_id) === item.id),
        ),
    );

    function onLineMaterialChange(line) {
        // Prefill with the material's last purchase cost — the buyer can override.
        const material = itemById.value.get(Number(line.inventory_item_id));

        line.unit_cost = material ? String(material.unit_cost) : '';
    }

    function addLine() {
        form.value.lines.push({ inventory_item_id: '', quantity: '', unit_cost: '' });
    }

    function removeLine(index) {
        form.value.lines.splice(index, 1);

        if (form.value.lines.length === 0) {
            addLine();
        }
    }

    /**
     * Live order total: Σ(quantity × unit cost) over filled lines only —
     * the same arithmetic the backend repeats inside its transaction.
     */
    const liveTotal = computed(() =>
        form.value.lines.reduce((total, line) => {
            const quantity = parseFloat(line.quantity) || 0;
            const unitCost = parseInt(line.unit_cost, 10) || 0;

            return total + quantity * unitCost;
        }, 0),
    );

    const filledLineCount = computed(
        () => form.value.lines.filter((line) => line.inventory_item_id && parseFloat(line.quantity) > 0).length,
    );

    function submit() {
        submitting.value = true;

        router.post(
            route(options.submitRouteName, options.routeParams ?? {}),
            {
                supplier_id: form.value.supplier_id,
                notes: form.value.notes,
                lines: form.value.lines.map((line) => ({
                    inventory_item_id: line.inventory_item_id,
                    quantity: line.quantity,
                    unit_cost: line.unit_cost,
                })),
                ...(spoofMethod === 'put' ? { _method: 'put' } : {}),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    submitting.value = false;
                },
            },
        );
    }

    return {
        form,
        submitting,
        isTaken,
        hasFreeItems,
        onLineMaterialChange,
        addLine,
        removeLine,
        liveTotal,
        filledLineCount,
        submit,
    };
}
