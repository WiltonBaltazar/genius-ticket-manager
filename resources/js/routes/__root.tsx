import { createRootRoute, Outlet } from "@tanstack/react-router";
import { CartProvider } from "../lib/cart";

export const rootRoute = createRootRoute({
    component: RootComponent,
});

function RootComponent() {
    return (
        <CartProvider>
            <Outlet />
        </CartProvider>
    );
}
