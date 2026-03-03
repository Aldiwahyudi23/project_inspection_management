<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VehicleApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.vehicle_api.url'), '/');
        $this->token   = config('services.vehicle_api.token');
    }

    public function getVehicleDetail(int $id): array
    {
        $url = "{$this->baseUrl}/vehicle/details/{$id}";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {
            Log::error('Vehicle API error', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Vehicle API error',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('vehicle') 
                ?? $response->json('data') 
                ?? $response->json(),
        ];
    }
    public function getVehicleForInspectionForm(int $id): array
    {
        // http://vehicle-management-system.test/api/v1/inspection/form/vehicle/15
        $url = "{$this->baseUrl}/v1/inspection/form/vehicle/{$id}";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {
            Log::error('Vehicle API error', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Vehicle API error',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('vehicle') 
                ?? $response->json('data') 
                ?? $response->json(),
        ];
    }
}
