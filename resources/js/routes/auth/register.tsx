import { createRoute } from "@tanstack/react-router";
import { rootRoute } from "../__root";
import { AuthLayout } from "../../components/auth/AuthLayout";
import { RegisterForm } from "../../components/auth/RegisterForm";

export const registerRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/auth/register",
    component: RegisterPage,
});

function RegisterPage() {
    return (
        <AuthLayout
            eyebrow="Admit One"
            headline="Genius Behind the Brands"
            tagline="The annual gathering for the people building tomorrow's brands. Reserve your spot below."
        >
            <RegisterForm />
        </AuthLayout>
    );
}
