/**
 * Guest cart — persisted to localStorage per table QR token.
 *
 * Shape of a line: { productId, name, price, quantity }
 */

const STORAGE_PREFIX = 'veekitchen.cart.';

/**
 * Load the cart for a scope (table token, or "public").
 */
export function loadCart(scope = 'public') {
    try {
        const raw = localStorage.getItem(STORAGE_PREFIX + scope);

        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

/**
 * Persist the cart for a scope.
 */
export function saveCart(lines, scope = 'public') {
    try {
        localStorage.setItem(STORAGE_PREFIX + scope, JSON.stringify(lines));
    } catch {
        // Storage full / disabled — the in-memory cart still works.
    }
}

/**
 * Add a product to the cart (or bump its quantity).
 */
export function addToCart(lines, product, quantity = 1) {
    const existing = lines.find((line) => line.productId === product.id);

    if (existing) {
        return lines.map((line) =>
            line.productId === product.id
                ? { ...line, quantity: Math.min(99, line.quantity + quantity) }
                : line,
        );
    }

    return [
        ...lines,
        {
            productId: product.id,
            name: product.name,
            price: product.price,
            quantity,
        },
    ];
}

/**
 * Change a line's quantity; 0 removes it.
 */
export function setQuantity(lines, productId, quantity) {
    if (quantity <= 0) {
        return lines.filter((line) => line.productId !== productId);
    }

    return lines.map((line) =>
        line.productId === productId ? { ...line, quantity: Math.min(99, quantity) } : line,
    );
}

/**
 * Clear the cart after successful placement.
 */
export function clearCart(scope = 'public') {
    try {
        localStorage.removeItem(STORAGE_PREFIX + scope);
    } catch {
        // ignore
    }
}

/**
 * Cart total (raw sum; the server re-prices and ceiling-rounds anyway).
 */
export function cartTotal(lines) {
    return lines.reduce((sum, line) => sum + line.price * line.quantity, 0);
}
