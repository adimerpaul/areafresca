<?php

return [
    'enabled' => (bool) env('SIAT_ENABLED', true),
    'base_url' => rtrim(env('URL_SIAT', 'https://siatrest.impuestos.gob.bo/v2/'), '/').'/',
    'portal_url' => rtrim(env('URL_SIAT2', 'https://siat.impuestos.gob.bo/'), '/').'/',
    'wsdl' => env('SIAT_WSDL', rtrim(env('URL_SIAT', 'https://siatrest.impuestos.gob.bo/v2/'), '/').'/ServicioFacturacionCompraVenta?wsdl'),
    'codigo_sistema' => env('CODIGO_SISTEMA', env('SIAT_CODIGO_SISTEMA')),
    'ambiente' => (int) env('AMBIENTE', env('SIAT_AMBIENTE', 2)),
    'modalidad' => (int) env('MODALIDAD', env('SIAT_MODALIDAD', 1)),
    'sucursal' => (int) env('SIAT_SUCURSAL', 0),
    'punto_venta' => (int) env('SIAT_PUNTO_VENTA', 0),
    'actividad_economica' => env('SIAT_ACTIVIDAD_ECONOMICA'),
    'codigo_producto_sin' => env('SIAT_CODIGO_PRODUCTO_SIN') !== null ? (int) env('SIAT_CODIGO_PRODUCTO_SIN') : null,
    'unidad_medida' => (int) env('SIAT_UNIDAD_MEDIDA', 58),
    'municipio' => env('SIAT_MUNICIPIO', 'Oruro'),
    'leyenda' => env('SIAT_LEYENDA', 'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.'),
];
