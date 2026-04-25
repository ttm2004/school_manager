<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;    
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function districts(Request $request){
        $provideID = $request->query('province_id');

        if(!$provideID){
            return response()->json([
                'error' => 'Missing province_id parameter'
            ], 400);
        }

        $districts = DB::table('districts')
            ->where('province_id', $provideID)
            ->where('provide_iid',(int) $provideID)
            ->orderBy('name')
            ->get();
        // nếu không tìm thấy quận nào thì trả về lỗi
        if($districts->isEmpty()){
            return response()->json([
                'error' => 'No districts found for the given province_id'
            ], 404);
        }
        return response()->json($districts);
    }
}
