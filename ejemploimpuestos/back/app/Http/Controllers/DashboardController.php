<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $hoy         = Carbon::today();
        $inicioSemana = Carbon::today()->subDays(6);
        $inicioMes    = Carbon::today()->startOfMonth();
        $hace30       = Carbon::today()->subDays(30);

        $dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        // KPIs
        $totalHoy    = Venta::where('estado', '!=', 'Anulado')->whereDate('fecha', $hoy)->sum('total');
        $totalSemana = Venta::where('estado', '!=', 'Anulado')->whereDate('fecha', '>=', $inicioSemana)->sum('total');
        $totalMes    = Venta::where('estado', '!=', 'Anulado')->whereDate('fecha', '>=', $inicioMes)->sum('total');
        $ventasHoy   = Venta::where('estado', '!=', 'Anulado')->whereDate('fecha', $hoy)->count();

        // Ventas por día (últimos 7 días)
        $porDia = Venta::where('estado', '!=', 'Anulado')
            ->whereDate('fecha', '>=', $inicioSemana)
            ->select(DB::raw('fecha, SUM(total) as total_dia, COUNT(*) as cantidad_dia'))
            ->groupBy('fecha')
            ->get()
            ->keyBy('fecha');

        $diasSemana = [];
        for ($i = 6; $i >= 0; $i--) {
            $date    = Carbon::today()->subDays($i);
            $fechaStr = $date->format('Y-m-d');
            $venta   = $porDia->get($fechaStr);
            $diasSemana[] = [
                'label'    => $dayNames[$date->dayOfWeek] . ' ' . $date->format('d/m'),
                'total'    => $venta ? (float) $venta->total_dia : 0,
                'cantidad' => $venta ? (int) $venta->cantidad_dia : 0,
            ];
        }

        // Top 5 productos más vendidos (últimos 30 días)
        $topProductos = VentaDetalle::join('ventas', 'venta_detalles.venta_id', '=', 'ventas.id')
            ->join('productos', 'venta_detalles.producto_id', '=', 'productos.id')
            ->where('ventas.estado', '!=', 'Anulado')
            ->whereDate('ventas.fecha', '>=', $hace30)
            ->whereNull('ventas.deleted_at')
            ->whereNull('venta_detalles.deleted_at')
            ->select(
                'productos.nombre',
                'productos.imagen',
                DB::raw('SUM(venta_detalles.cantidad) as total_cantidad'),
                DB::raw('SUM(venta_detalles.cantidad * venta_detalles.precio) as total_monto')
            )
            ->groupBy('productos.id', 'productos.nombre', 'productos.imagen')
            ->orderByDesc('total_cantidad')
            ->limit(5)
            ->get();

        // Usuario que más vendió (este mes)
        $topUsuario = Venta::join('users', 'ventas.user_id', '=', 'users.id')
            ->where('ventas.estado', '!=', 'Anulado')
            ->whereDate('ventas.fecha', '>=', $inicioMes)
            ->whereNull('ventas.deleted_at')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(ventas.id) as total_ventas'),
                DB::raw('SUM(ventas.total) as total_monto')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_monto')
            ->first();

        return response()->json([
            'kpis' => [
                'total_hoy'    => (float) $totalHoy,
                'total_semana' => (float) $totalSemana,
                'total_mes'    => (float) $totalMes,
                'ventas_hoy'   => (int) $ventasHoy,
            ],
            'ventas_semana' => $diasSemana,
            'top_productos' => $topProductos,
            'top_usuario'   => $topUsuario,
        ]);
    }
}
