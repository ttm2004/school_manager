<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        $majors = DB::table('majors')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $admissionMethods = DB::table('admission_methods')
            ->where('status', 'active')
            ->orderBy('priority')
            ->get();

        $subjectCombinations = DB::table('subject_combinations')
            ->orderBy('code')
            ->get();

        return view('public.index', compact(
            'majors',
            'admissionMethods',
            'subjectCombinations'
        ));
    }
}