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

class ElectronicInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Venta $sale,
        private string $pdfPath,
        private string $xmlPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Area Fresca - Factura N° '.$this->sale->id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.factura-electronica');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => Storage::disk('local')->get($this->pdfPath), "Factura_{$this->sale->id}.pdf")
                ->withMime('application/pdf'),
            Attachment::fromData(fn () => Storage::disk('local')->get($this->xmlPath), "Factura_{$this->sale->id}.xml")
                ->withMime('application/xml'),
        ];
    }
}
