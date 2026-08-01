<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;

class CorreoPruebaController extends Controller
{
    public function enviar()
    {
        $destinatario = 'adimer101@gmail.com';

        Mail::raw('Hola desde Area Fresca. El envío de correo funciona correctamente.', function ($message) use ($destinatario) {
            $message->to($destinatario)->subject('Prueba de correo - Area Fresca');
        });

        return response()->json(['message' => 'Correo de prueba enviado correctamente', 'destinatario' => $destinatario]);
    }
}
