<?php

namespace Crater;

use Illuminate\Database\Eloquent\Model;

class TaxIdentificationType extends Model
{
    protected $table = 'iti_types';
    protected $fillable = [
        'name',
        'codigo_afip',
        'tax_type_id'
    ];

    public function taxType()
    {
        return $this->belongsTo(TaxType::class);
    }

}
