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
            <div className="flex gap-2" role="group" aria-label="Payment method">
                <button
                    type="button"
                    aria-pressed={method === "whatsapp"}
                    onClick={() => setMethod("whatsapp")}
                    className={`flex-1 rounded-md border px-4 py-2 font-condensed text-sm font-semibold uppercase tracking-wide ${
                        method === "whatsapp"
                            ? "border-deep-purple bg-deep-purple text-white"
                            : "border-deep-purple/20 text-deep-purple"
                    }`}
                >
                    Pagar via WhatsApp
                </button>
                <button
                    type="button"
                    aria-pressed={method === "bank"}
                    onClick={() => setMethod("bank")}
                    className={`flex-1 rounded-md border px-4 py-2 font-condensed text-sm font-semibold uppercase tracking-wide ${
                        method === "bank"
                            ? "border-deep-purple bg-deep-purple text-white"
                            : "border-deep-purple/20 text-deep-purple"
                    }`}
                >
                    Pagar por transferência bancária
                </button>
            </div>

            {method === "whatsapp" ? (
                <div className="rounded-md border border-deep-purple/10 p-4">
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
                            className="mt-4 block w-full rounded-md bg-deep-purple px-4 py-3 text-center font-condensed text-sm font-semibold uppercase tracking-wide text-white hover:bg-gold hover:text-deep-purple"
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
                <div className="rounded-md border border-deep-purple/10 p-4 font-sans text-sm text-deep-purple">
                    <p>
                        <span className="font-semibold">Nome da conta:</span>{" "}
                        {bankDetails.accountName}
                    </p>
                    <p>
                        <span className="font-semibold">
                            Número da conta:
                        </span>{" "}
                        {bankDetails.accountNumber}
                    </p>
                    <p>
                        <span className="font-semibold">Banco:</span>{" "}
                        {bankDetails.bankName}
                    </p>
                    <p>
                        <span className="font-semibold">Balcão:</span>{" "}
                        {bankDetails.branch}
                    </p>
                    <p className="mt-3">
                        <span className="font-semibold">Referência:</span>{" "}
                        {order.id}
                    </p>
                    <p>
                        <span className="font-semibold">Montante:</span> MZN{" "}
                        {order.total_amount}
                    </p>
                    {bankDetails.instructions && (
                        <p className="mt-3 whitespace-pre-line text-deep-purple/70">
                            {bankDetails.instructions}
                        </p>
                    )}
                </div>
            )}

            <div>
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
                    className="mt-2 block w-full font-sans text-sm text-deep-purple"
                />
                <button
                    type="button"
                    onClick={handleSubmit}
                    disabled={!selectedFile || uploading}
                    className="mt-3 rounded-md bg-deep-purple px-4 py-2 font-condensed text-sm font-semibold uppercase tracking-wide text-white hover:bg-gold hover:text-deep-purple disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {uploading ? "A enviar…" : "Enviar comprovativo"}
                </button>
                {justUploaded && (
                    <p
                        role="status"
                        className="mt-2 font-sans text-xs font-medium text-green-700"
                    >
                        Comprovativo enviado — a nossa equipa irá analisá-lo
                        em breve.
                    </p>
                )}
                {!justUploaded && order.proof_of_payment_uploaded && (
                    <p className="mt-1 font-sans text-xs text-deep-purple/50">
                        Recebido — a nossa equipa irá analisá-lo em breve.
                    </p>
                )}
                {uploadError && (
                    <p role="alert" className="mt-1 font-sans text-xs text-red-text">
                        {uploadError}
                    </p>
                )}
            </div>
        </div>
    );
}
