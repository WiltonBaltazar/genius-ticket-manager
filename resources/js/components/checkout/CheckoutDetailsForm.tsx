import { useEffect, useState, type FormEvent } from "react";
import { useCart } from "../../lib/cart";
import { AuthApiError, getJson, postJson } from "../../lib/auth";
import { FormField } from "../auth/FormField";

type SessionPayload = {
    attendee: { id: string; name: string; email: string } | null;
};

type OrderResponse = {
    order: { id: string; status: string; total_amount: string };
};

/** Details/review step: pre-fills from an active attendee session, otherwise a guest name/email form, plus a cart review with total before submit (spec.md User Story 2). */
export function CheckoutDetailsForm({
    onSubmitted,
}: {
    onSubmitted: (orderId: string) => void;
}) {
    const cart = useCart();
    const [session, setSession] = useState<SessionPayload["attendee"]>();
    const [name, setName] = useState("");
    const [email, setEmail] = useState("");
    const [phone, setPhone] = useState("");
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [transactionHash] = useState(() => crypto.randomUUID());

    useEffect(() => {
        getJson<SessionPayload>("/session").then((data) =>
            setSession(data.attendee),
        );
    }, []);

    const loggedIn = Boolean(session);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (!cart.eventId || cart.items.length === 0) return;

        setSubmitting(true);
        setErrors({});
        setFormError(null);

        try {
            const response = await postJson<OrderResponse>("/checkout", {
                transaction_hash: transactionHash,
                event_id: cart.eventId,
                items: cart.items.map((item) => ({
                    ticket_type_id: item.ticketType.id,
                    quantity: item.quantity,
                })),
                name: loggedIn ? null : name,
                email: loggedIn ? null : email,
                phone: loggedIn ? null : phone,
            });

            cart.clear();
            onSubmitted(response.order.id);
        } catch (err) {
            if (err instanceof AuthApiError && err.body.errors) {
                const next: Record<string, string> = {};
                for (const [field, messages] of Object.entries(
                    err.body.errors,
                )) {
                    next[field] = messages[0];
                }
                setErrors(next);
            } else {
                setFormError("Something went wrong. Please try again.");
            }
        } finally {
            setSubmitting(false);
        }
    }

    if (!cart.eventId || cart.items.length === 0) {
        return (
            <p className="font-sans text-base text-deep-purple/70">
                Your cart is empty.
            </p>
        );
    }

    return (
        <form noValidate onSubmit={handleSubmit} className="space-y-6">
            {formError && (
                <div
                    role="alert"
                    className="rounded-md border border-red/30 bg-red/5 px-4 py-3 font-sans text-sm font-medium text-red-text"
                >
                    {formError}
                </div>
            )}

            <ul className="divide-y divide-deep-purple/10 rounded-md border border-deep-purple/10">
                {cart.items.map((item) => (
                    <li
                        key={item.ticketType.id}
                        className="flex items-center justify-between px-4 py-3 font-sans text-sm text-deep-purple"
                    >
                        <span>
                            {item.quantity} × {item.ticketType.name}
                        </span>
                        <span>
                            MZN{" "}
                            {(
                                Number(item.ticketType.price) * item.quantity
                            ).toFixed(2)}
                        </span>
                    </li>
                ))}
                <li className="flex items-center justify-between px-4 py-3 font-sans text-sm font-semibold text-deep-purple">
                    <span>Total</span>
                    <span>MZN {cart.total.toFixed(2)}</span>
                </li>
            </ul>

            {!loggedIn && session !== undefined && (
                <div className="space-y-5">
                    <FormField
                        label="Full name"
                        name="name"
                        type="text"
                        autoComplete="name"
                        required
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        error={errors.name}
                    />
                    <FormField
                        label="Email address"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        error={errors.email}
                    />
                    <FormField
                        label="Phone number"
                        name="phone"
                        type="tel"
                        autoComplete="tel"
                        required
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        error={errors.phone}
                    />
                </div>
            )}

            {loggedIn && (
                <p className="font-sans text-sm text-deep-purple/70">
                    Booking as{" "}
                    <span className="font-semibold text-deep-purple">
                        {session?.name} ({session?.email})
                    </span>
                </p>
            )}

            <button
                type="submit"
                disabled={submitting}
                className="w-full rounded-md bg-deep-purple px-4 py-3 font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {submitting ? "Submitting…" : "Submit order"}
            </button>
        </form>
    );
}
