<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $table = 'registrations';

    public $timestamps = false;

    protected $filltable = [
        'fullname',
        'birthday',
        'gender',
        'indentification',
        'phone',
        'email',
        'address',
        'graduation_year',
        'school',
        'major',
        'method',
        'combination_id',
        'province_id',
        'district_id',
        'file_path',
        'status',
        'created_at'
    ];
}
