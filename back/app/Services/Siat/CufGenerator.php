<?php

declare(strict_types=1);

namespace App\Services\Siat;

class CufGenerator
{
    /**
     * Genera el Código Único de Factura y agrega el código de control del CUFD.
     */
    public function generate(
        string $nit,
        string $timestamp,
        int $branch,
        int $modality,
        int $emission,
        int $invoice,
        int $pos,
        string $control,
    ): string {
        $numericChain = str_pad($nit, 13, '0', STR_PAD_LEFT);
        $numericChain .= $timestamp;
        $numericChain .= str_pad((string) $branch, 4, '0', STR_PAD_LEFT);
        $numericChain .= (string) $modality;
        $numericChain .= (string) $emission;
        $numericChain .= '1'; // Código de documento fiscal: factura.
        $numericChain .= '01'; // Código de documento sector: compra y venta.
        $numericChain .= str_pad((string) $invoice, 10, '0', STR_PAD_LEFT);
        $numericChain .= str_pad((string) $pos, 4, '0', STR_PAD_LEFT);

        $numericChain .= $this->calculateMod11Digit($numericChain);

        return $this->toBase16($numericChain).$control;
    }

    /**
     * Calcula el dígito Módulo 11 definido por el SIN.
     *
     * El residuo 10 se representa con 1. Anexar "10" era el error que
     * producía un CUF inválido para determinadas fechas y números de factura.
     */
    private function calculateMod11Digit(string $value): string
    {
        $sum = 0;
        $multiplier = 2;

        for ($index = strlen($value) - 1; $index >= 0; $index--) {
            $sum += $multiplier * (int) $value[$index];
            $multiplier++;

            if ($multiplier > 9) {
                $multiplier = 2;
            }
        }

        $digit = $sum % 11;

        if ($digit === 10) {
            return '1';
        }

        return (string) $digit;
    }

    /** Convierte una cadena decimal grande a hexadecimal usando BCMath. */
    private function toBase16(string $number): string
    {
        $hexadecimal = '';

        while (bccomp($number, '0') > 0) {
            $remainder = (int) bcmod($number, '16');
            $hexadecimal = strtoupper(dechex($remainder)).$hexadecimal;
            $number = bcdiv($number, '16', 0);
        }

        return $hexadecimal !== '' ? $hexadecimal : '0';
    }
}
