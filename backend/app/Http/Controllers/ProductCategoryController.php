<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;

class ProductCategoryController extends Controller
{
    /** Categories for the Product reminder form and table. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ProductCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
