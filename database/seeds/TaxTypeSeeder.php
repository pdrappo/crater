<?php

use Illuminate\Database\Seeder;
use Crater\TaxType;

class TaxTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TaxType::create([
            'name' => 'No Gravado',
            'codigo_afip' => 1,
            'percent' => 0,
            'company_id' => 1,
            'compound_tax' => '',
            'collective_tax' => '',
            'description' => ''
            ]);

        TaxType::create([
            'name' => 'Exento',
            'codigo_afip' => 2,
            'percent' => 0,
            'company_id' => 1,
            'compound_tax' => '',
            'collective_tax' => '',
            'description' => ''
            ]);

        TaxType::create([
            'name' => 'NETO',
            'codigo_afip' => 3,
            'percent' => 0,
            'company_id' => 1,
            'compound_tax' => '',
            'collective_tax' => '',
            'description' => ''
            ]);

        TaxType::create([
            'name' => 'IVA',
            'codigo_afip' => 5,
            'percent' => 21,
            'company_id' => 1,
            'compound_tax' => '',
            'collective_tax' => '',
            'description' => ''
            ]);
    }
}
