import { createRoute } from "@tanstack/react-router";
import { rootRoute } from "../__root";
import { AuthLayout } from "../../components/auth/AuthLayout";
import { LoginForm } from "../../components/auth/LoginForm";

type LoginSearch = {
    verified?: string;
    verification?: string;
};

export const loginRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/auth/login",
    // TanStack Router's default search parser tries to coerce numeric-looking values
    // (e.g. `?verified=1` -> the number 1, not the string "1") — coerce with String()
    // rather than a `typeof === 'string'` check, which would silently drop it.
    validateSearch: (search: Record<string, unknown>): LoginSearch => ({
        verified: search.verified != null ? String(search.verified) : undefined,
        verification:
            search.verification != null
                ? String(search.verification)
                : undefined,
    }),
    component: LoginPage,
});

function LoginPage() {
    const { verified, verification } = loginRoute.useSearch();

    return (
        <AuthLayout
            eyebrow="Welcome Back"
            headline="Genius Behind the Brands"
            tagline="Sign in to manage your tickets for this year's event."
        >
            <LoginForm
                verified={verified === "1"}
                verificationFailed={verification === "failed"}
            />
        </AuthLayout>
    );
}
