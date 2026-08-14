import { createRoute, useNavigate } from "@tanstack/react-router";
import { rootRoute } from "./__root";
import { CheckoutDetailsForm } from "../components/checkout/CheckoutDetailsForm";

export const checkoutRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/checkout",
    component: CheckoutPage,
});

function CheckoutPage() {
    const navigate = useNavigate();

    return (
        <div className="mx-auto max-w-xl px-6 py-12">
            <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-gold">
                Finalizar compra
            </p>
            <h1 className="mt-3 font-display text-3xl text-deep-purple">
                Os seus dados
            </h1>

            <div className="mt-8">
                <CheckoutDetailsForm
                    onSubmitted={(orderId) =>
                        navigate({ to: `/orders/${orderId}` })
                    }
                />
            </div>
        </div>
    );
}
