<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenggarapLahanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'nama' => $this->nama,
            'nama_kth' => $this->nama_kth,
            'no_hp' => $this->no_hp,
            'luas_garapan' => (float) $this->luas_garapan,
            'lama_menggarap' => $this->lama_menggarap,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'utm_x' => $this->utm_x,
            'utm_y' => $this->utm_y,
            'utm_epsg' => $this->utm_epsg,
            'gps_accuracy_m' => $this->gps_accuracy_m === null ? null : (float) $this->gps_accuracy_m,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'foto_url' => $this->foto_path === null ? null : route('sensus.photo', $this->id, false).'?v='.substr(hash('sha256', $this->foto_storage.':'.$this->foto_path), 0, 12),
        ];
    }
}
