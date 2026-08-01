<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SiatCufd extends Model { use SoftDeletes; protected $guarded=[]; protected function casts(): array{return ['vence_en'=>'datetime'];} }
