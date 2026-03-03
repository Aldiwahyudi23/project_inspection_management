<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Http\Resources\InspectionDetailResource;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
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


}
