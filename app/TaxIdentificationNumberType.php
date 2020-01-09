<?php

namespace Crater;

use Illuminate\Database\Eloquent\Model;

class TaxIdentificationNumberType extends Model
{
    protected $table = 'itin_types';
    protected $fillable = [
        'name',
        'codigo_afip'
    ];

}
