<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class BulkSmsBdClient
{
    /**
     * @return array{ok: bool, http_status: ?int, body: ?string, exception?: string}
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

            return [
                'ok' => $response->successful(),
                'http_status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'http_status' => null,
                'body' => null,
                'exception' => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        $key = config('services.bulksms.api_key');
        $sender = config('services.bulksms.sender_id');

        return is_string($key) && $key !== ''
            && is_string($sender) && $sender !== '';
    }
}
