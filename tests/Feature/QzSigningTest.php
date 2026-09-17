<?php

namespace Tests\Feature;

use App\Http\Middleware\VerificarAccesoEmpresa;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QzSigningTest extends TestCase
{
    public function test_signature_requires_authentication(): void
    {
        $this->postJson('/api/etiquetas/qztray/sign', ['request' => '{}'])->assertUnauthorized();
    }

    public function test_signature_requires_print_permission(): void
    {
        $this->login(false);
        $this->postJson('/api/etiquetas/qztray/sign', ['request' => '{}'])->assertForbidden();
    }

    public function test_rejects_arbitrary_hash_and_device_operations(): void
    {
        $this->login();
        foreach ([str_repeat('a', 64), $this->message('file.write', []), $this->message('socket.sendData', [])] as $request) {
            $this->postJson('/api/etiquetas/qztray/sign', compact('request'))->assertUnprocessable();
        }
    }

    public function test_rejects_remote_destination_active_html_and_expired_messages(): void
    {
        $this->login();
        $base = ['printer' => ['name' => 'POS-80-Series'], 'options' => ['copies' => 1],
            'data' => [['type' => 'pixel', 'format' => 'html', 'flavor' => 'plain', 'data' => '<p>Ticket</p>']]];
        $remote = $base;
        $remote['printer'] = ['host' => '127.0.0.1', 'port' => 1234];
        $active = $base;
        $active['data'][0]['data'] = '<script>alert(1)</script>';
        foreach ([$this->message('print', $remote), $this->message('print', $active),
            json_encode(['call' => 'printers.find', 'params' => [], 'timestamp' => 1])] as $request) {
            $this->postJson('/api/etiquetas/qztray/sign', compact('request'))->assertUnprocessable();
        }
    }

    public function test_signs_exact_qz_hash_for_discovery_ticket_label_and_raw(): void
    {
        $this->login();
        $keyPath = storage_path('qztray/private-key.pem');
        $certPath = storage_path('qztray/digital-certificate.txt');
        $key = openssl_pkey_get_private(file_get_contents($keyPath));
        $cert = openssl_x509_read(file_get_contents($certPath));
        $this->assertNotFalse($key);
        $publicKey = openssl_pkey_get_public($cert);
        try {
            $jobs = [
                $this->message('printers.find', []),
                $this->message('printers.getDefault', null),
            ];
            foreach ([
                ['type' => 'pixel', 'format' => 'html', 'flavor' => 'plain', 'data' => '<html><body>Ticket</body></html>'],
                ['type' => 'pixel', 'format' => 'html', 'flavor' => 'plain', 'data' => '<style>@page {size:62mm 29mm}</style><img src="data:image/png;base64,AAAA">'],
                ['type' => 'raw', 'format' => 'command', 'flavor' => 'hex', 'data' => '1b405052554542410a1b64041d5601'],
            ] as $item) {
                $jobs[] = $this->message('print', ['printer' => ['name' => 'POS-80-Series'], 'options' => ['copies' => 1], 'data' => [$item]]);
            }
            foreach ($jobs as $request) {
                $response = $this->postJson('/api/etiquetas/qztray/sign', compact('request'))->assertOk();
                $hash = hash('sha256', $request);
                $response->assertJsonPath('hash', $hash);
                $this->assertSame(1, openssl_verify($hash, base64_decode($response->json('signature')),
                    $publicKey, OPENSSL_ALGO_SHA512));
            }
        } finally { }
    }

    private function login(bool $allowed = true): void
    {
        $this->withoutMiddleware(VerificarAccesoEmpresa::class);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 123;
        $user->shouldReceive('tienePermiso')->andReturn($allowed);
        Sanctum::actingAs($user);
    }

    private function message(string $call, ?array $params): string
    {
        return json_encode(['call' => $call, 'params' => $params, 'timestamp' => (int) (microtime(true) * 1000)]);
    }
}
