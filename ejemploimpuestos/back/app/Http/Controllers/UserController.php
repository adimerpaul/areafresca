<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller{
    function usersSucursal(Request $request){
        $user = $request->user();
//        error_log('sucursal: ' . $user->sucursal);
        if ($user->sucursal == 'Ayacucho') {
            return User::where('sucursal', $user->sucursal)
                ->where('id', '!=', 0)
                ->orderBy('id', 'desc')
                ->get();
        }else if ($user->sucursal == 'Oquendo') {
            return User::where('sucursal', $user->sucursal)
                ->where('id', '!=', 0)
                ->orderBy('id', 'desc')
                ->get();
        }
//        return User::where('sucursal', $sucursal)
//            ->where('id', '!=', 0)
//            ->orderBy('id', 'desc')
//            ->get();
    }
    function login(Request $request){
        $credentials = $request->only('username', 'password');
        $user = User::where('username', $credentials['username'])->first();
        if (!$user || !password_verify($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Usuario o contraseña incorrectos',
            ], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'token' => $token,
            'user' => $user,
            'datos' => [
                'nit' => env('NIT'),
                'razon' => env("RAZON"),
                'direccion' => env("DIRECCION"),
                'telefono' => env("TELEFONO"),
                'url' => env("URL_SIAT"),
                'url2' => env("URL_SIAT2")
            ]
        ]);
    }
    function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Token eliminado',
        ]);
    }
    function me(Request $request){
//        return $request->user();
        $user = User::where('id', $request->user()->id)
            ->firstOrFail();
        return [
            'user' => $user,
            'datos' => [
                'nit' => env('NIT'),
                'razon' => env("RAZON"),
                'direccion' => env("DIRECCION"),
                'telefono' => env("TELEFONO"),
                'url' => env("URL_SIAT"),
                'url2' => env("URL_SIAT2")
            ]
        ];
    }
    function index(){
        return User::where('id', '!=', 0)
            ->orderBy('id', 'desc')
            ->get();
    }
    function update(Request $request, $id){
        $user = User::find($id);
        $user->update($request->except('password'));
        return $user;
    }
    function updatePassword(Request $request, $id){
        $user = User::find($id);
        $user->update([
            'password' => bcrypt($request->password),
        ]);
        return $user;
    }
    function store(Request $request){
        $validatedData = $request->validate([
            'username' => 'required|unique:users',
            'password' => 'required',
            'name' => 'required',
//            'email' => 'required|email|unique:users',
        ]);
        $user = User::create($request->all());
        return $user;
    }
    function destroy($id){
        return User::destroy($id);
    }
}
