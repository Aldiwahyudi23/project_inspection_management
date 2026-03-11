<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Http\Resources\InspectionDetailResource;
use Illuminate\Http\Request;
use App\Services\VehicleApiService;

class InspectionController extends Controller
{
        public function __construct(
        protected VehicleApiService $vehicleApi
    ) {
        //
    }

    public function show(Inspection $inspection)
    {
        $inspection->load([
            'template:id,name',
            'results.sectionItem.menuSection',
            'images',
            'repairEstimations',
        ]);

        return new InspectionDetailResource($inspection);
    }

    //Hanya menerima atau mengirim id saja untuk keperluan pencarian di Backend
    public function search(Request $request)
    {
        $query = Inspection::query();

        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', "%{$search}%")
                ->orWhere('vehicle_name', 'like', "%{$search}%")
                ->orWhere('inspection_code', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->select('id')->get()
        ]);
    }


    ////==========================Untuk keperluan menentukan kendaraan ==================

    /**
     * Get brands for dropdown (dipanggil frontend)
     */
    public function getBrands()
    {
        $result = $this->vehicleApi->getBrands();
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch brands'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get models by brand (dipanggil frontend)
     */
    public function getModels(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer'
        ]);

        $result = $this->vehicleApi->getModels($request->brand_id);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch models'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get types by brand and model (dipanggil frontend)
     */
    public function getTypes(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer'
        ]);

        $result = $this->vehicleApi->getTypes(
            $request->brand_id, 
            $request->model_id
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch types'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get years by brand, model, type (dipanggil frontend)
     */
    public function getYears(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id' => 'required|integer'
        ]);

        $result = $this->vehicleApi->getYears(
            $request->brand_id,
            $request->model_id,
            $request->type_id
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch years'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get CC by brand, model, type, year (dipanggil frontend)
     */
    public function getCc(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id' => 'required|integer',
            'year' => 'required|integer'
        ]);

        $result = $this->vehicleApi->getCc(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch CC options'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get transmissions (dipanggil frontend)
     */
    public function getTransmissions(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id' => 'required|integer',
            'year' => 'required|integer',
            'cc' => 'required|integer'
        ]);

        $result = $this->vehicleApi->getTransmissions(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transmissions'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get fuel types (dipanggil frontend)
     */
    public function getFuelTypes(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id' => 'required|integer',
            'year' => 'required|integer',
            'cc' => 'required|integer',
            'transmission_id' => 'required|integer'
        ]);

        $result = $this->vehicleApi->getFuelTypes(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc,
            $request->transmission_id
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fuel types'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get market periods (dipanggil frontend)
     */
    public function getMarketPeriods(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id' => 'required|integer',
            'year' => 'required|integer',
            'cc' => 'required|integer',
            'transmission_id' => 'required|integer',
            'fuel_type' => 'required|string'
        ]);

        $result = $this->vehicleApi->getMarketPeriods(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc,
            $request->transmission_id,
            $request->fuel_type
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch market periods'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Get final vehicle detail ID (dipanggil frontend)
     */
    public function getVehicleDetail(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer',
            'model_id' => 'required|integer',
            'type_id' => 'required|integer',
            'year' => 'required|integer',
            'cc' => 'required|integer',
            'transmission_id' => 'required|integer',
            'fuel_type' => 'required|string',
            'market_period' => 'nullable|string'
        ]);

        $result = $this->vehicleApi->getVehicleDetailFromSelection(
            $request->brand_id,
            $request->model_id,
            $request->type_id,
            $request->year,
            $request->cc,
            $request->transmission_id,
            $request->fuel_type,
            $request->market_period
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicle detail'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    /**
     * Search vehicles (dipanggil frontend)
     */
    public function searchVehicles(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $result = $this->vehicleApi->searchVehicles(
            $request->q,
            $request->limit ?? 20
        );
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search vehicles'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

}
