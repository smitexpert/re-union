<?php

namespace Tests\Feature;

use App\Services\Sms\BulkSmsBdClient;
use Illuminate\Http\Client\ConnectionException;
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

        $result = (new BulkSmsBdClient)->send('8801712345678', 'hello');

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

        $result = (new BulkSmsBdClient)->send('8801712345678', 'hello');

        $this->assertFalse($result['ok']);
        $this->assertSame(500, $result['http_status']);
        $this->assertSame('Server Error', $result['body']);
    }

    public function test_returns_ok_true_when_2xx_body_has_response_code_202(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        $body = json_encode([
            'response_code' => 202,
            'success_message' => 'SMS Submitted Successfully',
            'error_message' => '',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response($body, 200),
        ]);

        $result = (new BulkSmsBdClient)->send('8801712345678', 'hello');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['http_status']);
        $this->assertSame(202, $result['provider_code']);
        $this->assertNull($result['provider_error']);
    }

    public function test_returns_ok_false_when_2xx_body_has_non_202_response_code(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        $body = json_encode([
            'response_code' => 1006,
            'success_message' => '',
            'error_message' => 'Unsuccessfull!! Balance Validity Not Available in this naharsharmin Account',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response($body, 200),
        ]);

        $result = (new BulkSmsBdClient)->send('8801712345678', 'hello');

        $this->assertFalse($result['ok']);
        $this->assertSame(200, $result['http_status']);
        $this->assertSame(1006, $result['provider_code']);
        $this->assertStringContainsString('Balance Validity Not Available', (string) $result['provider_error']);
    }

    public function test_returns_ok_true_when_2xx_body_is_non_json(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        Http::fake([
            'bulksmsbd.net/*' => Http::response('OK', 200),
        ]);

        $result = (new BulkSmsBdClient)->send('8801712345678', 'hello');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['http_status']);
        $this->assertNull($result['provider_code']);
        $this->assertNull($result['provider_error']);
    }

    public function test_catches_exceptions_and_returns_failure(): void
    {
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => 'TESTID',
        ]);

        Http::fake(function () {
            throw new ConnectionException('cURL: timeout');
        });

        $result = (new BulkSmsBdClient)->send('8801712345678', 'hello');

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
        $this->assertFalse((new BulkSmsBdClient)->isConfigured());

        config(['services.bulksms.api_key' => 'k', 'services.bulksms.sender_id' => '']);
        $this->assertFalse((new BulkSmsBdClient)->isConfigured());

        config(['services.bulksms.api_key' => 'k', 'services.bulksms.sender_id' => 's']);
        $this->assertTrue((new BulkSmsBdClient)->isConfigured());
    }
}
