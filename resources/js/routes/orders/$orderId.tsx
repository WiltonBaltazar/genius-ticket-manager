import { createRoute } from "@tanstack/react-router";
import { useCallback, useEffect, useState } from "react";
import { rootRoute } from "../__root";
import { getJson } from "../../lib/auth";
import { OrderStatus } from "../../components/checkout/OrderStatus";
import { PaymentMethodStep } from "../../components/checkout/PaymentMethodStep";

type OrderPayload = {
    order: {
        id: string;
        status: "pending" | "paid" | "expired";
        total_amount: string;
        expires_at?: string;
        proof_of_payment_uploaded: boolean;
        items: {
            ticket_type_name: string;
            event_date: string | null;
            quantity: number;
            unit_price: string;
            subtotal: string;
        }[];
        tickets: {
            id: string;
            ticket_type_name: string;
            event_date: string | null;
            pdf_url: string;
        }[];
    };
};

export const orderStatusRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "/orders/$orderId",
    component: OrderStatusPage,
});

function OrderStatusPage() {
    const { orderId } = orderStatusRoute.useParams();
    const [data, setData] = useState<OrderPayload | null>(null);
    const [notFound, setNotFound] = useState(false);

    const reload = useCallback(() => {
        getJson<OrderPayload>(`/orders/${orderId}`)
            .then(setData)
            .catch(() => setNotFound(true));
    }, [orderId]);

    useEffect(() => {
        reload();
    }, [reload]);

    if (notFound) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple">
                <p>Este pedido não está disponível.</p>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="mx-auto max-w-2xl px-6 py-24 text-center font-sans text-deep-purple/50">
                A carregar…
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-white font-sans">
            <div className="mx-auto max-w-xl px-6 py-12 sm:py-16">
                <OrderStatus order={data.order} />

                {data.order.status === "pending" && (
                    <div className="mt-10">
                        <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple">
                            Concluir pagamento
                        </p>
                        <div className="mt-4">
                            <PaymentMethodStep
                                order={data.order}
                                whatsappNumber={
                                    window.__CHECKOUT_CONFIG__.whatsappNumber
                                }
                                bankDetails={
                                    window.__CHECKOUT_CONFIG__.bankTransfer
                                }
                                onUploaded={reload}
                            />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
