import { useRef, useState, type ChangeEvent } from "react";
import { postFormData } from "../../lib/auth";

type OrderSummary = {
    id: string;
    total_amount: string;
    proof_of_payment_uploaded: boolean;
};

/** Payment step for a pending order: a WhatsApp deep link (order ref/total/status-page URL pre-filled) or bank-transfer instructions, plus a proof-of-payment upload (spec.md FR-007-FR-009, FR-019-FR-021). */
export function PaymentMethodStep({
    order,
    whatsappNumber,
    bankDetails,
    onUploaded,
}: {
    order: OrderSummary;
    whatsappNumber: string | null;
    bankDetails: {
        accountName: string | null;
        accountNumber: string | null;
        nib: string | null;
        bankName: string | null;
        branch: string | null;
        instructions: string | null;
    };
    onUploaded: () => void;
}) {
    const [method, setMethod] = useState<"whatsapp" | "bank">("whatsapp");
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [justUploaded, setJustUploaded] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const statusUrl = `${window.location.origin}/orders/${order.id}`;
    const message = `Olá! Gostaria de concluir o pagamento do pedido ${order.id} (MZN ${order.total_amount}). ${statusUrl}`;
    const waLink = whatsappNumber
        ? `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`
        : null;

    function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
        setSelectedFile(e.target.files?.[0] ?? null);
        setUploadError(null);
        setJustUploaded(false);
    }

    async function handleSubmit() {
        if (!selectedFile) return;

        setUploading(true);
        setUploadError(null);

        try {
            const form = new FormData();
            form.append("file", selectedFile);
            await postFormData(`/orders/${order.id}/proof-of-payment`, form);
            setJustUploaded(true);
            setSelectedFile(null);
            if (fileInputRef.current) fileInputRef.current.value = "";
            onUploaded();
        } catch {
            setUploadError("Não foi possível enviar o ficheiro. Tente novamente.");
        } finally {
            setUploading(false);
        }
    }

    return (
        <div className="space-y-6">
            {/* Stacks on a narrow phone, side-by-side from sm: up — "Transferência bancária"
                doesn't fit a half-width pill below ~420px, and a rounded-full pill with
                wrapped text looks broken rather than just tight. */}
            <div
                className="flex flex-col gap-1 rounded-2xl bg-deep-purple/5 p-1 sm:flex-row sm:rounded-full"
                role="group"
                aria-label="Payment method"
            >
                <button
                    type="button"
                    aria-pressed={method === "whatsapp"}
                    onClick={() => setMethod("whatsapp")}
                    className={`flex-1 rounded-full px-4 py-2 font-condensed text-xs font-semibold uppercase tracking-wide transition-colors ${
                        method === "whatsapp"
                            ? "bg-white text-deep-purple shadow-sm"
                            : "text-deep-purple/50"
                    }`}
                >
                    WhatsApp
                </button>
                <button
                    type="button"
                    aria-pressed={method === "bank"}
                    onClick={() => setMethod("bank")}
                    className={`flex-1 rounded-full px-4 py-2 font-condensed text-xs font-semibold uppercase tracking-wide transition-colors ${
                        method === "bank"
                            ? "bg-white text-deep-purple shadow-sm"
                            : "text-deep-purple/50"
                    }`}
                >
                    Transferência bancária
                </button>
            </div>

            {method === "whatsapp" ? (
                <div className="rounded-lg border border-deep-purple/10 p-5">
                    <p className="font-sans text-sm text-deep-purple/70">
                        Envie-nos uma mensagem no WhatsApp para combinar o
                        pagamento — a referência do pedido e o total já estão
                        preenchidos.
                    </p>
                    {waLink ? (
                        <a
                            href={waLink}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-4 block w-full rounded-full bg-deep-purple px-4 py-3 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple"
                        >
                            Abrir WhatsApp
                        </a>
                    ) : (
                        <p className="mt-4 font-sans text-sm text-red-text">
                            O pagamento via WhatsApp ainda não está
                            configurado — utilize a transferência bancária.
                        </p>
                    )}
                </div>
            ) : (
                <div className="rounded-lg border border-deep-purple/10 p-5">
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-4">
                        <div>
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                Nome da conta
                            </dt>
                            <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                                {bankDetails.accountName}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                Número da conta
                            </dt>
                            <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                                {bankDetails.accountNumber}
                            </dd>
                        </div>
                        {bankDetails.nib && (
                            // col-span-2: NIB (21 digits in MZ) is long enough to feel
                            // cramped sharing a 2-col row — always give it the full width.
                            <div className="col-span-2">
                                <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                    NIB
                                </dt>
                                <dd className="mt-1 font-sans text-sm font-medium text-deep-purple break-all">
                                    {bankDetails.nib}
                                </dd>
                            </div>
                        )}
                        <div>
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                Banco
                            </dt>
                            <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                                {bankDetails.bankName}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                Balcão
                            </dt>
                            <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                                {bankDetails.branch}
                            </dd>
                        </div>
                    </dl>

                    {/* Referência is the order's full UUID (needed for exact bank-statement
                        reconciliation, not the truncated Pedido # shown elsewhere) — too long to
                        share a row with Montante below ~420px without squeezing it into a
                        broken mid-number wrap, so this stacks until there's room. */}
                    <dl className="mt-5 flex flex-col gap-3 border-t border-deep-purple/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                Referência
                            </dt>
                            <dd className="mt-1 font-sans text-sm font-semibold text-deep-purple break-all">
                                {order.id}
                            </dd>
                        </div>
                        <div className="sm:text-right">
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                                Montante
                            </dt>
                            <dd className="mt-1 font-display text-lg whitespace-nowrap text-deep-purple">
                                MZN {order.total_amount}
                            </dd>
                        </div>
                    </dl>

                    {bankDetails.instructions && (
                        <p className="mt-4 whitespace-pre-line font-sans text-sm text-deep-purple/70">
                            {bankDetails.instructions}
                        </p>
                    )}
                </div>
            )}

            <div className="rounded-lg border border-dashed border-deep-purple/25 p-5">
                <label
                    htmlFor="proof-of-payment"
                    className="font-condensed text-xs font-semibold uppercase tracking-wide text-deep-purple"
                >
                    {order.proof_of_payment_uploaded
                        ? "Substituir comprovativo de pagamento"
                        : "Enviar comprovativo de pagamento (opcional)"}
                </label>
                <input
                    id="proof-of-payment"
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,application/pdf"
                    disabled={uploading}
                    onChange={handleFileChange}
                    className="mt-3 block w-full font-sans text-sm text-deep-purple/70 file:mr-3 file:rounded-full file:border-0 file:bg-deep-purple/5 file:px-4 file:py-2 file:font-condensed file:text-xs file:font-semibold file:uppercase file:tracking-wide file:text-deep-purple"
                />
                <button
                    type="button"
                    onClick={handleSubmit}
                    disabled={!selectedFile || uploading}
                    className="mt-4 rounded-full bg-deep-purple px-5 py-2 font-condensed text-xs font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {uploading ? "A enviar…" : "Enviar comprovativo"}
                </button>
                {justUploaded && (
                    <p
                        role="status"
                        className="mt-3 font-sans text-xs font-medium text-green-700"
                    >
                        Comprovativo enviado — a nossa equipa irá analisá-lo
                        em breve.
                    </p>
                )}
                {!justUploaded && order.proof_of_payment_uploaded && (
                    <p className="mt-2 font-sans text-xs text-deep-purple/50">
                        Recebido — a nossa equipa irá analisá-lo em breve.
                    </p>
                )}
                {uploadError && (
                    <p role="alert" className="mt-2 font-sans text-xs text-red-text">
                        {uploadError}
                    </p>
                )}
            </div>
        </div>
    );
}
