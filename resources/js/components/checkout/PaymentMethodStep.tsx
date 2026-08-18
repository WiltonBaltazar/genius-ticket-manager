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

/** Referência/Montante block every non-WhatsApp method needs so the attendee sends the right amount against the right order — WhatsApp skips it since the pre-filled chat message already carries both. */
function ReferenceAndAmount({ order }: { order: OrderSummary }) {
    return (
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
    );
}

function MobileMoneyPanel({
    label,
    details,
    order,
}: {
    label: string;
    details: MobileMoneyDetails;
    order: OrderSummary;
}) {
    return (
        <div className="rounded-lg border border-deep-purple/10 p-5">
            <p className="font-sans text-sm text-deep-purple/70">
                Envie o valor abaixo para o número {label} indicado.
            </p>
            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-4">
                <div>
                    <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                        Número
                    </dt>
                    <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                        {details.number}
                    </dd>
                </div>
                {details.name && (
                    <div>
                        <dt className="font-condensed text-[11px] font-semibold uppercase tracking-wide text-deep-purple/40">
                            Nome registado
                        </dt>
                        <dd className="mt-1 font-sans text-sm font-medium text-deep-purple">
                            {details.name}
                        </dd>
                    </div>
                )}
            </dl>
            <ReferenceAndAmount order={order} />
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
            <div className="rounded-lg border border-deep-purple/10 p-5">
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

                <ReferenceAndAmount order={order} />

                {bankDetails.instructions && (
                    <p className="mt-4 whitespace-pre-line font-sans text-sm text-deep-purple/70">
                        {bankDetails.instructions}
                    </p>
                )}
            </div>
        );
    } else if (method === "emola") {
        panel = <MobileMoneyPanel label={MOBILE_MONEY_LABELS.emola} details={emola} order={order} />;
    } else if (method === "mpesa") {
        panel = <MobileMoneyPanel label={MOBILE_MONEY_LABELS.mpesa} details={mpesa} order={order} />;
    } else if (method === "mkesh") {
        panel = <MobileMoneyPanel label={MOBILE_MONEY_LABELS.mkesh} details={mkesh} order={order} />;
    }

    return (
        <div className="space-y-6">
            {methods.length === 0 ? (
                <p className="font-sans text-sm text-red-text">
                    Ainda não há nenhum método de pagamento configurado —
                    contacte-nos diretamente para concluir o pagamento.
                </p>
            ) : (
                <>
                    {/* Stacks on a narrow phone, side-by-side from sm: up — a long label
                        like "Transferência bancária" doesn't fit a half-width pill below
                        ~420px, and a rounded-full pill with wrapped text looks broken
                        rather than just tight. */}
                    <div
                        className="flex flex-col gap-1 rounded-2xl bg-deep-purple/5 p-1 sm:flex-row sm:flex-wrap sm:rounded-full"
                        role="group"
                        aria-label="Payment method"
                    >
                        {methods.map((m) => (
                            <button
                                key={m.id}
                                type="button"
                                aria-pressed={method === m.id}
                                onClick={() => setMethod(m.id)}
                                className={`flex-1 rounded-full px-4 py-2 font-condensed text-xs font-semibold uppercase tracking-wide transition-colors ${
                                    method === m.id
                                        ? "bg-white text-deep-purple shadow-sm"
                                        : "text-deep-purple/50"
                                }`}
                            >
                                {m.label}
                            </button>
                        ))}
                    </div>

                    {panel}
                </>
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
