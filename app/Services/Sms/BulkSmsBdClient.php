<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class BulkSmsBdClient
{
    /**
     * @return array{ok: bool, http_status: ?int, body: ?string, provider_code: ?int, provider_error: ?string, exception?: string}
     */
    public function send(string $phone, string $message): array
    {
        $payload = [
            'api_key' => (string) config('services.bulksms.api_key'),
            'senderid' => (string) config('services.bulksms.sender_id'),
            'number' => $phone,
            'message' => $message,
        ];

        $url = (string) config('services.bulksms.url', 'http://bulksmsbd.net/api/smsapi');

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->withOptions(['verify' => false])
                ->post($url, $payload);

            [$providerCode, $providerError] = self::parseProviderBody($response->body());

            $ok = $response->successful()
                && ($providerCode === null || $providerCode === 202);

            return [
                'ok' => $ok,
                'http_status' => $response->status(),
                'body' => $response->body(),
                'provider_code' => $providerCode,
                'provider_error' => $providerError,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'http_status' => null,
                'body' => null,
                'provider_code' => null,
                'provider_error' => null,
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private static function parseProviderBody(?string $body): array
    {
        if ($body === null || $body === '') {
            return [null, null];
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return [null, null];
        }

        $code = $decoded['response_code'] ?? null;
        $code = is_int($code) ? $code : null;

        $error = $decoded['error_message'] ?? null;
        $error = is_string($error) && $error !== '' ? $error : null;

        return [$code, $error];
    }

    public function isConfigured(): bool
    {
        $key = config('services.bulksms.api_key');
        $sender = config('services.bulksms.sender_id');

        return is_string($key) && $key !== ''
            && is_string($sender) && $sender !== '';
    }
}
