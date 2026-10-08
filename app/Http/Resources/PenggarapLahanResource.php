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
            'no_hp' => $this->no_hp,
            'luas_garapan' => (float) $this->luas_garapan,
            'lama_menggarap' => $this->lama_menggarap,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'utm_x' => $this->utm_x,
            'utm_y' => $this->utm_y,
            'utm_epsg' => $this->utm_epsg,
            'gps_accuracy_m' => $this->gps_accuracy_m === null ? null : (float) $this->gps_accuracy_m,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'foto_url' => route('sensus.photo', $this->id, false),
        ];
    }
}
