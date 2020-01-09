<?php

use Illuminate\Database\Seeder;
use Crater\TaxIdentificationNumberType;

class TaxIdentificationNumberTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TaxIdentificationNumberType::create(['name' => 'CUIT', 'codigo_afip' => 80]);
        TaxIdentificationNumberType::create(['name' => 'DNI', 'codigo_afip' => 96]);
        TaxIdentificationNumberType::create(['name' => 'Otro', 'codigo_afip' => 99]);
        TaxIdentificationNumberType::create(['name' => 'Pasaporte', 'codigo_afip' => 94]);
        TaxIdentificationNumberType::create(['name' => 'CI Extranjera', 'codigo_afip' => 91]);
        TaxIdentificationNumberType::create(['name' => 'CDI', 'codigo_afip' => 87]);

    }
}
