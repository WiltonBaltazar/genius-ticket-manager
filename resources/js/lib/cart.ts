import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from "react";
import { createElement } from "react";

/**
 * A purchasable variant of a ticket type: the full event pass (eventDate: null)
 * or one specific day of a multi-day event, at that day's own (already-divided)
 * price. Two variants of the same ticket type share one availableQuantity pool.
 */
export type CartTicketType = {
    id: string;
    name: string;
    price: string;
    availableQuantity: number;
    eventDate: string | null;
};

export type CartItem = {
    ticketType: CartTicketType;
    quantity: number;
};

type CartState = {
    eventId: string;
    eventSlug: string;
    items: CartItem[];
};

const STORAGE_KEY = "checkout-cart";

function readStoredCart(): CartState | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        return raw ? (JSON.parse(raw) as CartState) : null;
    } catch {
        return null;
    }
}

function writeStoredCart(cart: CartState | null): void {
    try {
        if (cart) {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
        } else {
            window.localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // localStorage unavailable (private browsing, etc.) — cart just
        // won't survive a reload; not fatal to the checkout flow itself.
    }
}

type CartContextValue = {
    eventId: string | null;
    items: CartItem[];
    /** Adds 1 of a ticket type variant (full pass or a specific day), clamped to the shared availableQuantity across all of that ticket type's variants already in the cart. Starting a cart for a different event clears any existing cart (an order can only span one event's ticket types). */
    add: (eventId: string, eventSlug: string, ticketType: CartTicketType) => void;
    setQuantity: (
        ticketTypeId: string,
        quantity: number,
        eventDate?: string | null,
    ) => void;
    remove: (ticketTypeId: string, eventDate?: string | null) => void;
    clear: () => void;
    total: number;
};

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
    const [cart, setCart] = useState<CartState | null>(() => readStoredCart());

    useEffect(() => {
        writeStoredCart(cart);
    }, [cart]);

    const add = useCallback(
        (eventId: string, eventSlug: string, ticketType: CartTicketType) => {
            setCart((prev) => {
                const base: CartState =
                    prev && prev.eventId === eventId
                        ? prev
                        : { eventId, eventSlug, items: [] };

                // All variants (full pass, each day) of a ticket type draw from
                // the same shared availableQuantity pool.
                const totalForType = base.items
                    .filter((item) => item.ticketType.id === ticketType.id)
                    .reduce((sum, item) => sum + item.quantity, 0);

                if (totalForType >= ticketType.availableQuantity) {
                    return base; // already at the availability ceiling
                }

                const existing = base.items.find(
                    (item) =>
                        item.ticketType.id === ticketType.id &&
                        item.ticketType.eventDate === ticketType.eventDate,
                );

                const items = existing
                    ? base.items.map((item) =>
                          item === existing
                              ? { ...item, quantity: item.quantity + 1 }
                              : item,
                      )
                    : [...base.items, { ticketType, quantity: 1 }];

                return { ...base, items };
            });
        },
        [],
    );

    const setQuantity = useCallback(
        (
            ticketTypeId: string,
            quantity: number,
            eventDate: string | null = null,
        ) => {
            setCart((prev) => {
                if (!prev) return prev;

                const target = prev.items.find(
                    (item) =>
                        item.ticketType.id === ticketTypeId &&
                        item.ticketType.eventDate === eventDate,
                );
                if (!target) return prev;

                const otherQuantityForType = prev.items
                    .filter(
                        (item) =>
                            item.ticketType.id === ticketTypeId &&
                            item !== target,
                    )
                    .reduce((sum, item) => sum + item.quantity, 0);

                const clamped = Math.max(
                    0,
                    Math.min(
                        quantity,
                        target.ticketType.availableQuantity -
                            otherQuantityForType,
                    ),
                );

                const items = prev.items
                    .map((item) =>
                        item === target
                            ? { ...item, quantity: clamped }
                            : item,
                    )
                    .filter((item) => item.quantity > 0);

                return { ...prev, items };
            });
        },
        [],
    );

    const remove = useCallback(
        (ticketTypeId: string, eventDate: string | null = null) => {
            setCart((prev) =>
                prev
                    ? {
                          ...prev,
                          items: prev.items.filter(
                              (item) =>
                                  !(
                                      item.ticketType.id === ticketTypeId &&
                                      item.ticketType.eventDate === eventDate
                                  ),
                          ),
                      }
                    : prev,
            );
        },
        [],
    );

    const clear = useCallback(() => setCart(null), []);

    const total = useMemo(
        () =>
            (cart?.items ?? []).reduce(
                (sum, item) => sum + Number(item.ticketType.price) * item.quantity,
                0,
            ),
        [cart],
    );

    const value: CartContextValue = {
        eventId: cart?.eventId ?? null,
        items: cart?.items ?? [],
        add,
        setQuantity,
        remove,
        clear,
        total,
    };

    return createElement(CartContext.Provider, { value }, children);
}

export function useCart(): CartContextValue {
    const context = useContext(CartContext);
    if (!context) {
        throw new Error("useCart must be used within a CartProvider");
    }
    return context;
}
