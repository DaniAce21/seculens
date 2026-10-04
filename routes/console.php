<?php

use App\Enums\AuditAction;
use App\Mail\SecurityReportMail;
use App\Models\IdsSensor;
use App\Services\AuditService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Sensores IDS (tokens de la API /api/v1/alerts)
|--------------------------------------------------------------------------
*/

Artisan::command('ids:sensor-create {name : Nombre unico del sensor, p. ej. sensor-dmz}', function (string $name) {
    if (IdsSensor::where('name', $name)->exists()) {
        $this->error("Ya existe un sensor llamado \"{$name}\".");

        return 1;
    }

    [$sensor, $token] = IdsSensor::register($name);

    app(AuditService::class)->tryLog(AuditAction::SENSOR_CREATED, null, $sensor, ['name' => $name]);

    $this->info("Sensor \"{$name}\" creado (id {$sensor->id}).");
    $this->newLine();
    $this->line('Token (se muestra SOLO ahora, guardalo en el sensor):');
    $this->line("  {$token}");
    $this->newLine();
    $this->line('Uso: Authorization: Bearer '.$token);

    return 0;
})->purpose('Registra un sensor IDS y muestra su token de API');

Artisan::command('ids:sensor-list', function () {
    $this->table(
        ['ID', 'Nombre', 'Ultimo uso', 'Estado', 'Alertas'],
        IdsSensor::withCount('alerts')->orderBy('name')->get()->map(fn (IdsSensor $s) => [
            $s->id,
            $s->name,
            $s->last_used_at?->format('Y-m-d H:i') ?? 'nunca',
            $s->revoked_at ? 'REVOCADO '.$s->revoked_at->format('Y-m-d') : 'activo',
            $s->alerts_count,
        ]),
    );
})->purpose('Lista los sensores IDS registrados');

Artisan::command('ids:sensor-revoke {name}', function (string $name) {
    $sensor = IdsSensor::where('name', $name)->whereNull('revoked_at')->first();

    if ($sensor === null) {
        $this->error("No hay un sensor activo llamado \"{$name}\".");

        return 1;
    }

    $sensor->forceFill(['revoked_at' => now()])->save();
    app(AuditService::class)->tryLog(AuditAction::SENSOR_REVOKED, null, $sensor, ['name' => $name]);

    $this->info("Sensor \"{$name}\" revocado: su token deja de funcionar inmediatamente.");

    return 0;
})->purpose('Revoca el token de un sensor IDS');

/*
|--------------------------------------------------------------------------
| Informe de seguridad por correo
|--------------------------------------------------------------------------
|
| Destinatarios en .env: REPORT_MAIL_TO="soc@empresa.com,otro@empresa.com"
| Sin --date envia el periodo ANTERIOR completo (p. ej. la semana pasada).
*/

Artisan::command('reports:send {--period=week : day|week|month|year} {--date= : fecha incluida en el periodo (Y-m-d)} {--to= : destinatarios separados por comas}', function () {
    $unit = $this->option('period');

    if (! array_key_exists($unit, Period::SLUGS)) {
        $this->error('Periodo invalido. Usa: '.implode(', ', array_keys(Period::SLUGS)));

        return 1;
    }

    $date = $this->option('date')
        ? CarbonImmutable::createFromFormat('Y-m-d', $this->option('date'))
        : CarbonImmutable::now()->sub(1, $unit);

    $period = Period::containing($unit, $date);

    $to = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) ($this->option('to') ?: config('services.reports.mail_to'))),
    )));

    if ($to === []) {
        $this->error('No hay destinatarios: define REPORT_MAIL_TO en .env o usa --to=correo@dominio.');

        return 1;
    }

    Mail::to($to)->send(new SecurityReportMail($period));

    $this->info('Informe "'.$period->description().'" enviado a: '.implode(', ', $to));

    return 0;
})->purpose('Envia por correo el informe de seguridad con los CSV adjuntos');

// Cada lunes a las 08:00 se envia el informe de la semana anterior.
// Requiere el planificador: "php artisan schedule:work" (desarrollo) o una
// tarea programada que ejecute "php artisan schedule:run" cada minuto.
Schedule::command('reports:send --period=week')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping();
