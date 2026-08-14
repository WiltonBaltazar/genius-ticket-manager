import { createRouter } from "@tanstack/react-router";
import { rootRoute } from "./routes/__root";
import { homeRoute } from "./routes/index";
import { registerRoute } from "./routes/auth/register";
import { loginRoute } from "./routes/auth/login";
import { forgotPasswordRoute } from "./routes/auth/forgot-password";
import { eventShowRoute } from "./routes/events/$slug";
import { checkoutRoute } from "./routes/checkout";
import { orderStatusRoute } from "./routes/orders/$orderId";

const routeTree = rootRoute.addChildren([
    homeRoute,
    registerRoute,
    loginRoute,
    forgotPasswordRoute,
    eventShowRoute,
    checkoutRoute,
    orderStatusRoute,
]);

export const router = createRouter({ routeTree, defaultPreload: "intent" });

declare module "@tanstack/react-router" {
    interface Register {
        router: typeof router;
    }
}
