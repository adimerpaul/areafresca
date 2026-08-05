<?php

namespace App\Services;

use App\Mail\ElectronicInvoiceMail;
use App\Mail\CancelledInvoiceMail;
use App\Models\Configuracion;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InvoiceDeliveryService
{
    public function generatePdf(Venta $sale): string
    {
        $sale->loadMissing(['detalles', 'cliente', 'usuario:id,name,username']);
        $company = Configuracion::firstOrFail();
        $queryUrl = rtrim(config('siat.portal_url'), '/').'/consulta/QR?'.http_build_query([
            'nit' => $company->nit,
            'cuf' => $sale->cuf,
            'numero' => $sale->id,
            't' => 2,
        ]);
        $qr = new Builder(
            writer: new PngWriter(),
            data: $queryUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 260,
            margin: 4,
        );
        $qrDataUri = $qr->build()->getDataUri();
        $pdf = Pdf::loadView('facturas.electronica', compact('sale', 'company', 'qrDataUri'))
            ->setPaper([0, 0, 226.77, 841.89]);
        $path = "impuestos/facturas/{$sale->id}.pdf";
        Storage::disk('local')->put($path, $pdf->output());
        $sale->update(['pdf_path' => $path]);

        return $path;
    }

    public function send(Venta $sale): bool
    {
        $email = $sale->cliente_email ?: $sale->cliente?->email;
        if (! $email) {
            return false;
        }
        if (! $sale->xml_path || ! Storage::disk('local')->exists($sale->xml_path)) {
            throw new RuntimeException('No se encontró el XML para enviar al cliente');
        }
        $pdfPath = $sale->pdf_path && Storage::disk('local')->exists($sale->pdf_path)
            ? $sale->pdf_path
            : $this->generatePdf($sale);

        Mail::to($email)->send(new ElectronicInvoiceMail($sale, $pdfPath, $sale->xml_path));
        $sale->update(['email_enviado_en' => now(), 'email_error' => null]);
        error_log('[CORREO][FACTURA] Enviado: '.json_encode(['venta_id' => $sale->id, 'destinatario' => $email], JSON_UNESCAPED_UNICODE));

        return true;
    }

    public function sendCancellation(Venta $sale, ?string $reason = null): bool
    {
        $sale->loadMissing(['detalles', 'cliente', 'usuario:id,name,username']);
        $email = $sale->cliente_email ?: $sale->cliente?->email;
        if (! $email) {
            return false;
        }

        // Se vuelve a generar para que el adjunto incluya la marca ANULADA.
        $pdfPath = $this->generatePdf($sale);
        Mail::to($email)->send(new CancelledInvoiceMail($sale->fresh(['detalles', 'cliente', 'usuario']), $reason, $pdfPath));
        $sale->update(['email_enviado_en' => now(), 'email_error' => null]);
        error_log('[CORREO][ANULACION] Enviado: '.json_encode(['venta_id' => $sale->id, 'destinatario' => $email, 'motivo' => $reason], JSON_UNESCAPED_UNICODE));

        return true;
    }
}
