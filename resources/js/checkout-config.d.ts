export {};

declare global {
    interface Window {
        __CHECKOUT_CONFIG__: {
            whatsappNumber: string | null;
            bankTransfer: {
                accountName: string | null;
                accountNumber: string | null;
                nib: string | null;
                bankName: string | null;
                branch: string | null;
                instructions: string | null;
            };
        };
    }
}
