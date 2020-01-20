<?php

use Illuminate\Database\Seeder;
use Crater\TaxIdentificationType;

class TaxIdentificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TaxIdentificationType::create(['name' => 'IVA Responsable Inscripto', 'codigo_afip' => 1]);
        TaxIdentificationType::create(['name' => 'IVA Sujeto Exento', 'codigo_afip' => 1]);
        TaxIdentificationType::create(['name' => 'Consumidor Final', 'codigo_afip' => 5]);
        TaxIdentificationType::create(['name' => 'Responsable Monotributo', 'codigo_afip' => 6]);
    }
}
