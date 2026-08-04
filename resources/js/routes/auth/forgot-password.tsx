import { createRoute } from "@tanstack/react-router";
import { rootRoute } from "../__root";
import { AuthLayout } from "../../components/auth/AuthLayout";
import { ForgotPasswordForm } from "../../components/auth/ForgotPasswordForm";

type ForgotPasswordSearch = {
    token?: string;
    email?: string;
};

export const forgotPasswordRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/auth/forgot-password",
    // Coerce with String() rather than a `typeof === 'string'` check — TanStack Router's
    // default search parser coerces numeric-looking values (see routes/auth/login.tsx).
    validateSearch: (
        search: Record<string, unknown>,
    ): ForgotPasswordSearch => ({
        token: search.token != null ? String(search.token) : undefined,
        email: search.email != null ? String(search.email) : undefined,
    }),
    component: ForgotPasswordPage,
});

function ForgotPasswordPage() {
    const { token, email } = forgotPasswordRoute.useSearch();

    return (
        <AuthLayout
            eyebrow={token ? "Set A New Password" : "Reset Your Password"}
            headline="Genius Behind the Brands"
            tagline={
                token
                    ? "Choose a new password to get back into your account."
                    : "We'll email you a secure link to get back in."
            }
        >
            <ForgotPasswordForm token={token} email={email} />
        </AuthLayout>
    );
}
