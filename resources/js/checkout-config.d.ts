export {};

declare global {
    interface Window {
        __CHECKOUT_CONFIG__: {
            whatsappNumber: string;
            bankTransfer: {
                accountName: string;
                accountNumber: string;
                bankName: string;
                branch: string;
            };
        };
    }
}
