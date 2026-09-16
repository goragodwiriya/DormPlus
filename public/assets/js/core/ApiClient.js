export class ApiError extends Error {
    constructor(message, status = 0, code = 'REQUEST_FAILED', fields = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.fields = fields;
    }
}

export class ApiClient {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.csrfToken = null;
    }

    setCsrfToken(token) {
        this.csrfToken = typeof token === 'string' ? token : null;
    }

    get(path, options = {}) {
        return this.request('GET', path, null, options);
    }

    post(path, data = {}, options = {}) {
        return this.request('POST', path, data, options);
    }

    put(path, data = {}, options = {}) {
        return this.request('PUT', path, data, options);
    }

    delete(path, options = {}) {
        return this.request('DELETE', path, null, options);
    }

    async request(method, path, data = null, options = {}) {
        const headers = new Headers({
            Accept: 'application/json',
            ...(options.headers || {})
        });

        const requestOptions = {
            method,
            headers,
            credentials: 'same-origin',
            signal: options.signal
        };

        if (data !== null) {
            headers.set('Content-Type', 'application/json');
            requestOptions.body = JSON.stringify(data);
        }

        if (
            this.csrfToken &&
            ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)
        ) {
            headers.set('X-CSRF-Token', this.csrfToken);
        }

        let response;

        try {
            response = await fetch(
                `${this.baseUrl}/${String(path).replace(/^\/+/, '')}`,
                requestOptions
            );
        } catch (error) {
            if (error.name === 'AbortError') {
                throw error;
            }

            throw new ApiError(
                'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                0,
                'NETWORK_ERROR'
            );
        }

        if (response.status === 204) {
            return null;
        }

        let result;

        try {
            result = await response.json();
        } catch {
            throw new ApiError(
                'เซิร์ฟเวอร์ตอบกลับในรูปแบบไม่ถูกต้อง',
                response.status,
                'INVALID_RESPONSE'
            );
        }

        if (!response.ok || result.success !== true) {
            const error = result.error || {};

            throw new ApiError(
                error.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
                response.status,
                error.code || 'REQUEST_FAILED',
                error.fields || {}
            );
        }

        return result;
    }
}