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

export type CartTicketType = {
    id: string;
    name: string;
    price: string;
    availableQuantity: number;
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
    /** Adds 1 of a ticket type, clamped to its availableQuantity. Starting a cart for a different event clears any existing cart (an order can only span one event's ticket types). */
    add: (eventId: string, eventSlug: string, ticketType: CartTicketType) => void;
    setQuantity: (ticketTypeId: string, quantity: number) => void;
    remove: (ticketTypeId: string) => void;
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

                const existing = base.items.find(
                    (item) => item.ticketType.id === ticketType.id,
                );
                const currentQuantity = existing?.quantity ?? 0;
                const nextQuantity = Math.min(
                    currentQuantity + 1,
                    ticketType.availableQuantity,
                );

                if (nextQuantity === currentQuantity) {
                    return base; // already at the availability ceiling
                }

                const items = existing
                    ? base.items.map((item) =>
                          item.ticketType.id === ticketType.id
                              ? { ...item, quantity: nextQuantity }
                              : item,
                      )
                    : [...base.items, { ticketType, quantity: nextQuantity }];

                return { ...base, items };
            });
        },
        [],
    );

    const setQuantity = useCallback((ticketTypeId: string, quantity: number) => {
        setCart((prev) => {
            if (!prev) return prev;

            const items = prev.items
                .map((item) => {
                    if (item.ticketType.id !== ticketTypeId) return item;
                    const clamped = Math.max(
                        0,
                        Math.min(quantity, item.ticketType.availableQuantity),
                    );
                    return { ...item, quantity: clamped };
                })
                .filter((item) => item.quantity > 0);

            return { ...prev, items };
        });
    }, []);

    const remove = useCallback((ticketTypeId: string) => {
        setCart((prev) =>
            prev
                ? {
                      ...prev,
                      items: prev.items.filter(
                          (item) => item.ticketType.id !== ticketTypeId,
                      ),
                  }
                : prev,
        );
    }, []);

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
