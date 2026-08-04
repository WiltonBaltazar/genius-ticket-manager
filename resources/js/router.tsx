import { createRouter } from "@tanstack/react-router";
import { rootRoute } from "./routes/__root";
import { registerRoute } from "./routes/auth/register";
import { loginRoute } from "./routes/auth/login";
import { forgotPasswordRoute } from "./routes/auth/forgot-password";

const routeTree = rootRoute.addChildren([
    registerRoute,
    loginRoute,
    forgotPasswordRoute,
]);

export const router = createRouter({ routeTree, defaultPreload: "intent" });

declare module "@tanstack/react-router" {
    interface Register {
        router: typeof router;
    }
}
