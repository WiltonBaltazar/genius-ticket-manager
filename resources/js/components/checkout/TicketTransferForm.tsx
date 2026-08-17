import { useState, type FormEvent } from "react";
import { AuthApiError, postJson } from "../../lib/auth";
import { FormField } from "../auth/FormField";

type TransferResponse = {
    ticket: { id: string; status: string; holder_name: string };
};

const REASON_MESSAGES: Record<string, string> = {
    already_checked_in: "Este bilhete já foi utilizado e não pode ser transferido.",
    voided: "Este bilhete foi anulado e não pode ser transferido.",
};

/** Inline name/email form for reassigning one unused ticket — no recipient account
 * needed (design choice), matching the order/ticket URL itself being the only
 * "authorization" this attendee-facing flow ever requires. */
export function TicketTransferForm({
    transferUrl,
    onTransferred,
    onCancel,
}: {
    transferUrl: string;
    onTransferred: () => void;
    onCancel: () => void;
}) {
    const [name, setName] = useState("");
    const [email, setEmail] = useState("");
    const [phone, setPhone] = useState("");
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        try {
            await postJson<TransferResponse>(transferUrl, {
                name,
                email,
                phone,
            });
            onTransferred();
        } catch (err) {
            if (err instanceof AuthApiError && err.body.errors) {
                const next: Record<string, string> = {};
                for (const [field, messages] of Object.entries(
                    err.body.errors,
                )) {
                    next[field] = messages[0];
                }
                setErrors(next);
            } else if (err instanceof AuthApiError) {
                const reason = (err.body as { error?: string }).error;
                setFormError(
                    (reason && REASON_MESSAGES[reason]) ??
                        "Não foi possível transferir este bilhete.",
                );
            } else {
                setFormError("Ocorreu um erro. Tente novamente.");
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <form
            noValidate
            onSubmit={handleSubmit}
            className="space-y-3 border-t border-dashed border-deep-purple/20 bg-deep-purple/[0.02] px-4 py-4"
        >
            {formError && (
                <p
                    role="alert"
                    className="font-sans text-sm font-medium text-red-text"
                >
                    {formError}
                </p>
            )}
            <FormField
                label="Nome de quem vai receber"
                name="name"
                autoComplete="name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                error={errors.name}
                required
            />
            <FormField
                label="E-mail de quem vai receber"
                name="email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                error={errors.email}
                required
            />
            <FormField
                label="Número de telefone de quem vai receber"
                name="phone"
                type="tel"
                autoComplete="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                error={errors.phone}
                required
            />
            <div className="flex items-center gap-4 pt-1">
                <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-full bg-deep-purple px-4 py-2 font-condensed text-xs font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {submitting ? "A transferir…" : "Confirmar transferência"}
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple/50 transition-colors hover:text-deep-purple"
                >
                    Cancelar
                </button>
            </div>
        </form>
    );
}
