<?php

use App\Http\Controllers\Api\FormInspectionController;
use App\Http\Controllers\API\VehicleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InspectionController;

/*
|--------------------------------------------------------------------------
| API Routes for Inspection Module (Filament Compatible)
|--------------------------------------------------------------------------
*/

// ln -s /home/u516139464/domains/cekmobil.online/public_html/project-management/management/storage/app/public /home/u516139464/domains/cekmobil.online/public_html/project-management/management/public/storage
Route::prefix('inspection')->group(function () {

    Route::get('/details/{inspection}', [InspectionController::class, 'show']);
    Route::get('/search', [InspectionController::class, 'search']);

    // Main endpoint for getting inspection template structure
    Route::get('{inspectionId}/template-structure', [FormInspectionController::class, 'getInspectionTemplate']);
    // Endpoint for uploading images
    Route::post('/inspection-images/upload', [FormInspectionController::class, 'uploadImages']);
    // Delete image endpoint
    Route::delete('/inspection-images/{id}', [FormInspectionController::class, 'deleteImage']);
    // Delete inspection endpoint
    Route::delete('/{inspectionId}/items/{itemId}', [FormInspectionController::class, 'deleteItem']);
    // Get image Item Null
    Route::get('/inspection-images/unassigned/{inspectionId}', [FormInspectionController::class, 'getUnassignedImages']);  

    // Update Inspection Item
    Route::patch(
        '/inspection-images/assign',
        [FormInspectionController::class, 'assignImages']
    );
    //Untuk update vehicle info di form inspection
    Route::put('/{inspectionId}/vehicle', [FormInspectionController::class, 'updateVehicle']);
    // Submit form with multiple items
    Route::post('/save-form', [FormInspectionController::class, 'saveForm']);

 // Vehicle selection endpoints (proxy ke Backend A)
    Route::prefix('vehicle-selection')->group(function () {
        Route::get('/brands', [InspectionController::class, 'getBrands']);
        Route::get('/models', [InspectionController::class, 'getModels']);
        Route::get('/types', [InspectionController::class, 'getTypes']);
        Route::get('/years', [InspectionController::class, 'getYears']);
        Route::get('/cc', [InspectionController::class, 'getCc']);
        Route::get('/transmissions', [InspectionController::class, 'getTransmissions']);
        Route::get('/fuel-types', [InspectionController::class, 'getFuelTypes']);
        Route::get('/market-periods', [InspectionController::class, 'getMarketPeriods']);
        Route::get('/get-detail', [InspectionController::class, 'getVehicleDetail']);
        Route::get('/search', [InspectionController::class, 'searchVehicles']);
    });

// yang bawah masih tanda tanya 
    
    
    
    // Get progress
    Route::get('{inspectionId}/progress', function ($inspectionId) {
        $inspection = \App\Models\Inspection::findOrFail($inspectionId);
        return response()->json([
            'success' => true,
            'data' => [
                'progress_percentage' => $inspection->progress_percentage,
                'total_items' => $inspection->sectionItems()->count(),
                'completed_items' => $inspection->results()->count(),
            ]
        ]);
    });
});

// Template endpoints (without inspection context)
Route::prefix('templates')->group(function () {
    Route::get('{templateId}/structure', [FormInspectionController::class, 'getTemplateStructure']);
    Route::get('{templateId}/preview', [FormInspectionController::class, 'getTemplatePreview']);
});

Route::get('/test', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API jalan'
    ]);
});

// 4|X7UtX7mRImr0FDmFekFRKxjMZQvXaeihxcroOM1a20ef4c00