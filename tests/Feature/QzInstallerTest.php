<?php

namespace Tests\Feature;

use Tests\TestCase;

class QzInstallerTest extends TestCase
{
    public function test_macos_download_contains_public_certificate_and_unix_line_endings(): void
    {
        $response = $this->get('/api/etiquetas/qztray/instalador?plataforma=macos');
        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="instalar-qztray-buhopos.command"');
        $script = $response->getContent();
        $this->assertStringStartsWith("#!/bin/bash\n", $script);
        $this->assertStringNotContainsString("\r", $script);
        $this->assertStringNotContainsString('{{CERTIFICATE}}', $script);
        preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $script, $matches);
        $this->assertNotEmpty($matches);
        $this->assertNotFalse(openssl_x509_parse($matches[0]));
        $this->assertStringNotContainsString('PRIVATE KEY', $script);
    }

    public function test_existing_download_still_defaults_to_windows(): void
    {
        $this->get('/api/etiquetas/qztray/instalador')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="instalar-qztray-buhopos.bat"');
    }

    public function test_unknown_platform_is_rejected(): void
    {
        $this->getJson('/api/etiquetas/qztray/instalador?plataforma=unknown')->assertUnprocessable();
    }
}
