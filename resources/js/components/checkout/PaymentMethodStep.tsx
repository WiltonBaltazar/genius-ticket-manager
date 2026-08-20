import { useRef, useState, type ChangeEvent, type ReactNode } from "react";
import { postFormData } from "../../lib/auth";

type OrderSummary = {
    id: string;
    total_amount: string;
    proof_of_payment_uploaded: boolean;
};

type BankDetails = {
    accountName: string | null;
    accountNumber: string | null;
    nib: string | null;
    bankName: string | null;
    branch: string | null;
    instructions: string | null;
};

type MobileMoneyDetails = {
    number: string | null;
    name: string | null;
};

type MethodId = "whatsapp" | "bank" | "emola" | "mpesa" | "mkesh";

const MOBILE_MONEY_LABELS: Record<"emola" | "mpesa" | "mkesh", string> = {
    emola: "E-Mola",
    mpesa: "M-Pesa",
    mkesh: "M-Kesh",
};

function CopyIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <rect x="8" y="8" width="12" height="12" rx="2" />
            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
        </svg>
    );
}

function CheckIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path d="M20 6 9 17l-5-5" />
        </svg>
    );
}

/** Copies a payment number so it can be pasted straight into a mobile money app rather than retyped digit by digit — a real accuracy win on a step where a mistyped number sends money to the wrong account. */
function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    async function handleCopy() {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard API unavailable (e.g. insecure context) — the number is
            // still right there on screen to copy by hand, so this is a silent no-op.
        }
    }

    return (
        <button
            type="button"
            onClick={handleCopy}
            aria-label={copied ? "Número copiado" : "Copiar número"}
            className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-deep-purple/40 transition-colors hover:bg-deep-purple/5 hover:text-deep-purple"
        >
            {copied ? <CheckIcon /> : <CopyIcon />}
        </button>
    );
}

/** Leads every non-WhatsApp panel with the amount and reference — the two things that must be entered correctly in the sending app — before the account details. */
function AmountLead({ order }: { order: OrderSummary }) {
    return (
        <div className="border-b border-dashed border-deep-purple/15 pb-4">
            <p className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                Valor a enviar
            </p>
            <p className="mt-1 font-display text-3xl text-deep-purple">
                MZN {order.total_amount}
            </p>
            <p className="mt-3 font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                Referência do pedido
            </p>
            <p className="mt-1 font-sans text-xs font-semibold tracking-wide text-deep-purple/70 break-all">
                {order.id}
            </p>
        </div>
    );
}

/** Shared by every non-WhatsApp panel (bank transfer and each mobile money method) so buyers stuck mid-payment have one consistent way to reach us instead of abandoning the order. */
function WhatsAppHelpLink({ waLink, question }: { waLink: string | null; question: string }) {
    if (!waLink) return null;

    return (
        <div className="mt-5 border-t border-dashed border-deep-purple/15 pt-4">
            <p className="font-sans text-xs text-deep-purple/60">
                {question}{" "}
                <a
                    href={waLink}
                    target="_blank"
                    rel="noreferrer"
                    className="font-semibold text-deep-purple underline decoration-deep-purple/30 underline-offset-2 transition-colors hover:text-gold-hover hover:decoration-gold-hover"
                >
                    Envie-nos uma mensagem no WhatsApp
                </a>
            </p>
        </div>
    );
}

function MobileMoneyPanel({
    label,
    details,
    order,
    waLink,
}: {
    label: string;
    details: MobileMoneyDetails;
    order: OrderSummary;
    waLink: string | null;
}) {
    return (
        <div>
            <AmountLead order={order} />
            <div className="mt-4">
                <p className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                    Número {label}
                </p>
                <div className="mt-1 flex items-center gap-1">
                    <p className="font-sans text-xl font-semibold tracking-wide text-deep-purple">
                        {details.number}
                    </p>
                    {details.number && <CopyButton value={details.number} />}
                </div>
            </div>
            {details.name && (
                <div className="mt-4">
                    <p className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                        Nome registado
                    </p>
                    <p className="mt-1 font-sans text-sm font-medium text-deep-purple">
                        {details.name}
                    </p>
                </div>
            )}
            <WhatsAppHelpLink waLink={waLink} question="Dúvidas sobre o pagamento?" />
        </div>
    );
}

/** Payment step for a pending order: WhatsApp, bank transfer, and/or mobile money (E-Mola/M-Pesa/M-Kesh) — only the methods the organizer has actually configured are offered — plus a proof-of-payment upload (spec.md FR-007-FR-009, FR-019-FR-021). */
export function PaymentMethodStep({
    order,
    whatsappNumber,
    bankDetails,
    emola,
    mpesa,
    mkesh,
    onUploaded,
}: {
    order: OrderSummary;
    whatsappNumber: string | null;
    bankDetails: BankDetails;
    emola: MobileMoneyDetails;
    mpesa: MobileMoneyDetails;
    mkesh: MobileMoneyDetails;
    onUploaded: () => void;
}) {
    const methods: { id: MethodId; label: string }[] = [
        ...(whatsappNumber ? [{ id: "whatsapp" as const, label: "WhatsApp" }] : []),
        ...(bankDetails.accountNumber
            ? [{ id: "bank" as const, label: "Transferência bancária" }]
            : []),
        ...(emola.number ? [{ id: "emola" as const, label: MOBILE_MONEY_LABELS.emola }] : []),
        ...(mpesa.number ? [{ id: "mpesa" as const, label: MOBILE_MONEY_LABELS.mpesa }] : []),
        ...(mkesh.number ? [{ id: "mkesh" as const, label: MOBILE_MONEY_LABELS.mkesh }] : []),
    ];

    const [method, setMethod] = useState<MethodId | null>(methods[0]?.id ?? null);
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

    let panel: ReactNode = null;
    if (method === "whatsapp") {
        panel = (
            <div>
                <p className="font-sans text-sm text-deep-purple/70">
                    Envie-nos uma mensagem no WhatsApp para combinar o
                    pagamento — a referência do pedido e o total já estão
                    preenchidos.
                </p>
                <a
                    href={waLink ?? undefined}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-4 block w-full rounded-full bg-deep-purple px-4 py-3 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-gold hover:text-deep-purple"
                >
                    Abrir WhatsApp
                </a>
            </div>
        );
    } else if (method === "bank") {
        panel = (
            <div>
                <AmountLead order={order} />
                <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-4">
                    <div>
                        <dt className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                            Nome da conta
                        </dt>
                        <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                            {bankDetails.accountName}
                        </dd>
                    </div>
                    <div>
                        <dt className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                            Número da conta
                        </dt>
                        <dd className="mt-1 flex items-center gap-1">
                            <span className="font-sans text-sm font-medium text-deep-purple">
                                {bankDetails.accountNumber}
                            </span>
                            {bankDetails.accountNumber && (
                                <CopyButton value={bankDetails.accountNumber} />
                            )}
                        </dd>
                    </div>
                    {bankDetails.nib && (
                        // col-span-2: NIB (21 digits in MZ) is long enough to feel
                        // cramped sharing a 2-col row — always give it the full width.
                        <div className="col-span-2">
                            <dt className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                                NIB
                            </dt>
                            <dd className="mt-1 flex items-center gap-1">
                                <span className="font-sans text-sm font-medium text-deep-purple break-all">
                                    {bankDetails.nib}
                                </span>
                                <CopyButton value={bankDetails.nib} />
                            </dd>
                        </div>
                    )}
                    <div>
                        <dt className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                            Banco
                        </dt>
                        <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                            {bankDetails.bankName}
                        </dd>
                    </div>
                    <div>
                        <dt className="font-condensed text-[11px] font-semibold uppercase tracking-[0.2em] text-deep-purple/40">
                            Balcão
                        </dt>
                        <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                            {bankDetails.branch}
                        </dd>
                    </div>
                </dl>

                {bankDetails.instructions && (
                    <p className="mt-4 whitespace-pre-line font-sans text-sm text-deep-purple/70">
                        {bankDetails.instructions}
                    </p>
                )}

                <WhatsAppHelpLink waLink={waLink} question="Dúvidas sobre a transferência?" />
            </div>
        );
    } else if (method === "emola") {
        panel = <MobileMoneyPanel label={MOBILE_MONEY_LABELS.emola} details={emola} order={order} waLink={waLink} />;
    } else if (method === "mpesa") {
        panel = <MobileMoneyPanel label={MOBILE_MONEY_LABELS.mpesa} details={mpesa} order={order} waLink={waLink} />;
    } else if (method === "mkesh") {
        panel = <MobileMoneyPanel label={MOBILE_MONEY_LABELS.mkesh} details={mkesh} order={order} waLink={waLink} />;
    }

    return (
        <div className="space-y-6">
            <div className="overflow-hidden rounded-2xl border border-deep-purple/10 shadow-sm">
                {/* Perforated seam — this card picks up where the ticket stub above tears off. */}
                <div aria-hidden="true" className="relative h-4 bg-deep-purple">
                    <span className="absolute inset-x-4 top-1/2 -translate-y-1/2 border-t-2 border-dashed border-white/20" />
                    <span className="absolute -left-2 top-1/2 h-4 w-4 -translate-y-1/2 rounded-full bg-white" />
                    <span className="absolute -right-2 top-1/2 h-4 w-4 -translate-y-1/2 rounded-full bg-white" />
                </div>

                <div className="bg-white px-6 pt-5 pb-6">
                    <p className="font-condensed text-xs font-semibold uppercase tracking-[0.3em] text-deep-purple/50">
                        Concluir pagamento
                    </p>

                    {methods.length === 0 ? (
                        <p className="mt-4 font-sans text-sm text-red-text">
                            Ainda não há nenhum método de pagamento
                            configurado — contacte-nos diretamente para
                            concluir o pagamento.
                        </p>
                    ) : (
                        <>
                            <div
                                className="no-scrollbar mt-4 flex gap-1 overflow-x-auto border-b border-deep-purple/10"
                                role="group"
                                aria-label="Payment method"
                            >
                                {methods.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        aria-pressed={method === m.id}
                                        onClick={() => setMethod(m.id)}
                                        className={`-mb-px shrink-0 whitespace-nowrap border-b-2 px-3 py-2.5 font-condensed text-xs font-semibold uppercase tracking-wide transition-colors ${
                                            method === m.id
                                                ? "border-gold text-deep-purple"
                                                : "border-transparent text-deep-purple/40 hover:text-deep-purple/70"
                                        }`}
                                    >
                                        {m.label}
                                    </button>
                                ))}
                            </div>

                            <div key={method} className="payment-panel-enter mt-5">
                                {panel}
                            </div>
                        </>
                    )}
                </div>
            </div>

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
