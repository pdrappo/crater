<?php
namespace Crater;

use Illuminate\Database\Eloquent\Model;
use Crater\User;
use Crater\CompanySetting;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class Company extends Model implements HasMedia
{
    use HasMediaTrait;

    protected $fillable = [
        'name',
        'logo',
        'unique_hash',
        'itin', // Numero de documento
        'itin_type_id', // Tipo de numero de documento
        'iti_type_id', // Tipo de tributo
        'iibb', // Numero de ingresos brutos
        'bad', // Fecha de inicio de actividades
    ];

    protected $appends=['logo'];

    public function getLogoAttribute()
    {
        $logo = $this->getMedia('logo')->first();
        if ($logo) {
            return  asset($logo->getUrl());
        }
        return ;
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function settings()
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function taxIdentificationNumberType()
    {
        return $this->belongsTo(TaxIdentificationNumberType::class, 'itin_type_id');
    }

    public function taxIdentificationType()
    {
        return $this->belongsTo(TaxIdentificationType::class, 'iti_type_id');
    }
}
