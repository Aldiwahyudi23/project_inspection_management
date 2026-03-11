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
    public function getVehicleDetailForInspectionForm(int $id): array
    {
        // http://vehicle-management-system.test/api/v1/inspection/form/vehicle/15
        $url = "{$this->baseUrl}/v1/inspection/form/vehicle-detail/{$id}";

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


    ///untuk keperluan mengambil data untuk create atau menentukan kendaraan
    
    /**
     * Get available brands
     * GET /vehicle/selection/brands
     */
    public function getBrands(): array
    {
        return $this->getSelectionData('brands');
    }

    /**
     * Get available models by brand
     * GET /vehicle/selection/models?brand_id={brandId}
     */
    public function getModels(int $brandId): array
    {
        return $this->getSelectionData('models', ['brand_id' => $brandId]);
    }

    /**
     * Get available types by brand and model
     * GET /vehicle/selection/types?brand_id={brandId}&model_id={modelId}
     */
    public function getTypes(int $brandId, int $modelId): array
    {
        return $this->getSelectionData('types', [
            'brand_id' => $brandId,
            'model_id' => $modelId
        ]);
    }

    /**
     * Get available years by brand, model, and type
     * GET /vehicle/selection/years?brand_id={brandId}&model_id={modelId}&type_id={typeId}
     */
    public function getYears(int $brandId, int $modelId, int $typeId): array
    {
        return $this->getSelectionData('years', [
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'type_id' => $typeId
        ]);
    }

    /**
     * Get available CC by brand, model, type, and year
     * GET /vehicle/selection/cc?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}
     */
    public function getCc(int $brandId, int $modelId, int $typeId, int $year): array
    {
        return $this->getSelectionData('cc', [
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'type_id' => $typeId,
            'year' => $year
        ]);
    }

    /**
     * Get available transmissions by brand, model, type, year, and cc
     * GET /vehicle/selection/transmissions?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}
     */
    public function getTransmissions(int $brandId, int $modelId, int $typeId, int $year, int $cc): array
    {
        return $this->getSelectionData('transmissions', [
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'type_id' => $typeId,
            'year' => $year,
            'cc' => $cc
        ]);
    }

    /**
     * Get available fuel types by brand, model, type, year, cc, and transmission
     * GET /vehicle/selection/fuel-types?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}&transmission_id={transmissionId}
     */
    public function getFuelTypes(int $brandId, int $modelId, int $typeId, int $year, int $cc, int $transmissionId): array
    {
        return $this->getSelectionData('fuel-types', [
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'type_id' => $typeId,
            'year' => $year,
            'cc' => $cc,
            'transmission_id' => $transmissionId
        ]);
    }

    /**
     * Get available market periods by complete selection
     * GET /vehicle/selection/market-periods?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}&transmission_id={transmissionId}&fuel_type={fuelType}
     */
    public function getMarketPeriods(int $brandId, int $modelId, int $typeId, int $year, int $cc, int $transmissionId, string $fuelType): array
    {
        return $this->getSelectionData('market-periods', [
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'type_id' => $typeId,
            'year' => $year,
            'cc' => $cc,
            'transmission_id' => $transmissionId,
            'fuel_type' => $fuelType
        ]);
    }

    /**
     * Get final vehicle detail ID from complete selection
     * GET /vehicle/selection/get-detail?brand_id={brandId}&model_id={modelId}&type_id={typeId}&year={year}&cc={cc}&transmission_id={transmissionId}&fuel_type={fuelType}&market_period={marketPeriod}
     */
    public function getVehicleDetailFromSelection(
        int $brandId, 
        int $modelId, 
        int $typeId, 
        int $year, 
        int $cc, 
        int $transmissionId, 
        string $fuelType, 
        ?string $marketPeriod = null
    ): array {
        $params = [
            'brand_id' => $brandId,
            'model_id' => $modelId,
            'type_id' => $typeId,
            'year' => $year,
            'cc' => $cc,
            'transmission_id' => $transmissionId,
            'fuel_type' => $fuelType
        ];

        if ($marketPeriod) {
            $params['market_period'] = $marketPeriod;
        }

        return $this->getSelectionData('get-detail', $params);
    }

    /**
     * Base method for selection endpoints
     */
    protected function getSelectionData(string $endpoint, array $params = []): array
    {
        $url = "{$this->baseUrl}/vehicle/selection/{$endpoint}";

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->get($url, $params);

        if ($response->failed()) {
            Log::error('Vehicle selection API error', [
                'url'      => $url,
                'params'   => $params,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Vehicle selection API error',
                'error'   => $response->json(),
            ];
        }

        return [
            'success' => true,
            'data'    => $response->json('data') ?? [],
        ];
    }

}
