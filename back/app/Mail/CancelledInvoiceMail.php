<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CancelledInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Venta $sale,
        public ?string $reason,
        private string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Area Fresca - Factura N° '.$this->sale->id.' ANULADA');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.factura-anulada');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => Storage::disk('local')->get($this->pdfPath), "Factura_{$this->sale->id}_ANULADA.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
