<?php

namespace Tests\Feature;

use App\Services\Sms\BulkSmsBdClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkSmsBdClientTest extends TestCase
{
    public function test_sends_form_post_to_provider_with_configured_credentials(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
            'services.bulksms.url' => 'http://bulksmsbd.net/api/smsapi',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response('OK', 200),
        ]);

        $result = (new BulkSmsBdClient())->send('8801712345678', 'hello');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['http_status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://bulksmsbd.net/api/smsapi'
                && $request->method() === 'POST'
                && $request->data() === [
                    'api_key' => 'test-key',
                    'senderid' => 'TESTID',
                    'number' => '8801712345678',
                    'message' => 'hello',
                ];
        });
    }

    public function test_returns_failure_on_non_2xx(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response('Server Error', 500),
        ]);

        $result = (new BulkSmsBdClient())->send('8801712345678', 'hello');

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['http_status']);
        $this->assertSame('Server Error', $result['body']);
    }

    public function test_catches_exceptions_and_returns_failure(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL: timeout');
        });

        $result = (new BulkSmsBdClient())->send('8801712345678', 'hello');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['http_status']);
        $this->assertStringContainsString('timeout', $result['exception']);
    }

    public function test_is_configured_returns_false_when_credentials_missing(): void
    {
        config([
            'services.bulksms.api_key' => '',
            'services.bulksms.sender_id' => 'TESTID',
        ]);
        $this->assertFalse((new BulkSmsBdClient())->isConfigured());

        config(['services.bulksms.api_key' => 'k', 'services.bulksms.sender_id' => '']);
        $this->assertFalse((new BulkSmsBdClient())->isConfigured());

        config(['services.bulksms.api_key' => 'k', 'services.bulksms.sender_id' => 's']);
        $this->assertTrue((new BulkSmsBdClient())->isConfigured());
    }
}