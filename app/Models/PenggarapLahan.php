<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mattiverse\Userstamps\Traits\Userstamps as TraitsUserstamps;

class PenggarapLahan extends Model
{
    use HasFactory, TraitsUserstamps;

    protected $table = 'penggarap_lahans';

    protected $fillable = [
        'nama',
        'no_hp',
        'luas_garapan',
        'lama_menggarap',
        'foto_path',
        'latitude',
        'longitude',
        'utm_x',
        'utm_y',
        'utm_epsg',
        'client_uuid',
        'gps_accuracy_m',
        'captured_at',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'luas_garapan' => 'decimal:2',
            'lama_menggarap' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'utm_x' => 'float',
            'utm_y' => 'float',
            'utm_epsg' => 'integer',
            'gps_accuracy_m' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }
}
