/**
 * Shared fetch wrapper for the attendee-auth endpoints (contracts/auth-api.md).
 * Session-based, same-origin: sends the CSRF token from the page's meta tag
 * (research.md §8) rather than any bearer/token scheme.
 */

export class AuthApiError extends Error {
    constructor(
        public status: number,
        public body: {
            message?: string;
            errors?: Record<string, string[]>;
            resend_available?: boolean;
        },
    ) {
        super(body.message ?? `Request failed with status ${status}`);
        this.name = "AuthApiError";
    }

    /** First validation error message for a given field, if any. */
    fieldError(field: string): string | undefined {
        return this.body.errors?.[field]?.[0];
    }

    get isUnverified(): boolean {
        return this.status === 423;
    }

    get isThrottled(): boolean {
        return this.status === 429;
    }
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? ""
    );
}

export async function authFetch<T = unknown>(
    path: string,
    options: RequestInit = {},
): Promise<T> {
    const response = await fetch(path, {
        ...options,
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            ...options.headers,
        },
    });

    const isJson = response.headers
        .get("content-type")
        ?.includes("application/json");
    const body = isJson ? await response.json() : {};

    if (!response.ok) {
        throw new AuthApiError(response.status, body);
    }

    return body as T;
}

export function postJson<T = unknown>(path: string, data: unknown): Promise<T> {
    return authFetch<T>(path, { method: "POST", body: JSON.stringify(data) });
}

export function getJson<T = unknown>(path: string): Promise<T> {
    return authFetch<T>(path, { method: "GET" });
}

/** Same CSRF/session handling as authFetch, but for a multipart/form-data body (file uploads) — no Content-Type override, the browser sets the multipart boundary. */
export async function postFormData<T = unknown>(
    path: string,
    data: FormData,
): Promise<T> {
    const response = await fetch(path, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken(),
        },
        body: data,
    });

    const isJson = response.headers
        .get("content-type")
        ?.includes("application/json");
    const body = isJson ? await response.json() : {};

    if (!response.ok) {
        throw new AuthApiError(response.status, body);
    }

    return body as T;
}
