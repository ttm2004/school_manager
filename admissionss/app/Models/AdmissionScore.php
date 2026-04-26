<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionScore extends Model
{
    protected $table = 'diemtuyensinh';

    public $timestamps = false;

    protected $filltable = [
        'registration_id',
        'method',
        'score_data',
        'total_score',
        'created_at',
    ];
}
