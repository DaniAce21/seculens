<?php

namespace App\Mail;

use App\Services\CsvExportService;
use App\Services\ReportService;
use App\Support\Period;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Informe de seguridad de un periodo, enviado por correo.
 *
 * Cuerpo: resumen con las cifras clave e IPs mas activas.
 * Adjuntos: CSV de incidentes, eventos, alertas y alertas IDS del periodo.
 */
class SecurityReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Period $period,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SecuLens — Informe de seguridad: '.$this->period->description(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.security-report',
            with: app(ReportService::class)->build($this->period),
        );
    }

    /**
     * Un CSV por recurso (mismo formato que la descarga web).
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $csv = app(CsvExportService::class);

        return array_map(
            fn (string $resource) => Attachment::fromData(
                fn () => $csv->toString($resource, $this->period),
                $csv->filename($resource, $this->period),
            )->withMime('text/csv'),
            array_keys(CsvExportService::RESOURCES),
        );
    }
}
