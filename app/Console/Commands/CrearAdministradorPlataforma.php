<?php

namespace App\Console\Commands;

use App\Models\PlataformaAdministrador;
use Illuminate\Console\Command;

class CrearAdministradorPlataforma extends Command
{
    protected $signature = 'plataforma:crear-admin {--nombre=} {--email=} {--password=}';
    protected $description = 'Crea o actualiza un administrador del portal de plataforma';

    public function handle(): int
    {
        $nombre = $this->option('nombre') ?: $this->ask('Nombre');
        $email = $this->option('email') ?: $this->ask('Correo');
        $password = $this->option('password') ?: $this->secret('Contraseña (mínimo 10 caracteres)');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 10) {
            $this->error('Revisa el correo y usa una contraseña de al menos 10 caracteres.');
            return self::FAILURE;
        }

        PlataformaAdministrador::updateOrCreate(
            ['email' => mb_strtolower($email)],
            ['nombre' => $nombre, 'password' => $password, 'activo' => true]
        );

        $this->info('Administrador de plataforma listo.');
        return self::SUCCESS;
    }
}
