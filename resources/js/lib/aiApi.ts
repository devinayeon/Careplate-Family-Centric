import type {
    ChatAnswer,
    ChatSessionItem,
    ChatTurnItem,
    ScanResult,
} from '@/types/ai';

/** The server's error envelope: { error: { code, message, retryable }, request_id }. Messages are Indonesian. */
export class AiApiError extends Error {
    constructor(
        readonly status: number,
        readonly code: string,
        message: string,
        readonly retryable: boolean,
        readonly retryAfter: number | null,
    ) {
        super(message);
    }

    /** Message plus the next step the person can take. */
    get helpText(): string {
        if (this.status === 429) {
            return this.retryAfter && this.retryAfter < 3600
                ? `${this.message} Coba lagi sekitar ${Math.ceil(this.retryAfter / 60)} menit lagi.`
                : `${this.message} Batas harian akan terisi lagi besok.`;
        }
        if (this.code === 'OCR_UNREADABLE')
            return `${this.message} Foto ulang tabel gizinya dengan cahaya yang cukup.`;
        if (this.status === 413)
            return `${this.message} Pakai foto yang lebih kecil.`;
        if (this.status === 401 || this.status === 419)
            return 'Sesi kamu berakhir. Muat ulang halaman lalu masuk lagi.';
        if (this.retryable) return `${this.message} Coba lagi.`;
        return this.message;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function call<T>(
    method: string,
    path: string,
    body?: FormData | Record<string, unknown>,
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-XSRF-TOKEN': xsrfToken(),
    };
    let payload: BodyInit | undefined;
    if (body instanceof FormData) {
        payload = body;
    } else if (body) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    let response: Response;
    try {
        response = await fetch(`/ai/v1/${path}`, {
            method,
            headers,
            body: payload,
            credentials: 'same-origin',
        });
    } catch {
        throw new AiApiError(
            0,
            'NETWORK',
            'Tidak bisa terhubung ke server.',
            true,
            null,
        );
    }

    if (response.status === 204) return undefined as T;

    const json = await response.json().catch(() => null);
    if (!response.ok) {
        const retryAfter = Number(response.headers.get('Retry-After')) || null;
        throw new AiApiError(
            response.status,
            json?.error?.code ?? 'UNKNOWN',
            json?.error?.message ?? 'Terjadi kesalahan di server.',
            Boolean(json?.error?.retryable),
            retryAfter,
        );
    }

    return json as T;
}

export const scanLabel = (image: File) => {
    const form = new FormData();
    form.append('image', image);

    return call<ScanResult>('POST', 'scan', form);
};

export const listSessions = () =>
    call<{ data: ChatSessionItem[] }>('GET', 'chat/sessions?limit=20');

export const createSession = () =>
    call<ChatSessionItem>('POST', 'chat/sessions', {});

export const deleteSession = (id: string) =>
    call<void>('DELETE', `chat/sessions/${id}`);

export const listTurns = (id: string) =>
    call<{ data: ChatTurnItem[] }>('GET', `chat/sessions/${id}/turns?limit=20`);

/** Every deliberate send needs a fresh clientTurnId; reusing one only replays the stored answer. */
export const sendTurn = (id: string, clientTurnId: string, message: string) =>
    call<ChatAnswer>('POST', `chat/sessions/${id}/turns`, {
        client_turn_id: clientTurnId,
        message,
    });
