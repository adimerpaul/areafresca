<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller{
    function index(){
        return Proveedor::all();
    }
    function store(Request $request){
        return Proveedor::create($request->all());
    }
    function update(Request $request, $id){
        $proveedor = Proveedor::find($id);
        $proveedor->update($request->all());
        return $proveedor;
    }
    function destroy($id){
        $proveedor = Proveedor::find($id);
        $proveedor->delete();
        return response()->json(['message' => 'Proveedor deleted successfully']);
    }
}
