<?php

namespace Crater\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;
use Crater\Company;

class NewInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear factura contra afip';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $company = Company::find(1);

        // Crear Factura
        $produccion = env('AFIP_INVOICE_PRODUCTION', true);
        $certificado = $produccion ? 'certificado-produccion.crt' : 'certificado-test.crt';
        $afip = new \Afip(
            array(
            'CUIT' => 23316829759,
            'res_folder' => storage_path('app/afip/certificados/'),
            'ta_folder' => storage_path('app/afip/tokens/'),
            'cert' => $certificado,
            'key' => 'pdrappo-clave-afip.key',
            'production' => $produccion
            )
        );

        $data = array(
            'CantReg' 	=> 1,  // Cantidad de comprobantes a registrar
            'PtoVta' 	=> env('AFIP_INVOICE_BUSINESS_POINT', 2),  // Punto de venta
            'CbteTipo' 	=> 11,  // Tipo de comprobante (ver tipos disponibles), (11) Factura C, (13) Nota de Credito C
            'Concepto' 	=> 1,  // Concepto del Comprobante: (1)Productos, (2)Servicios, (3)Productos y Servicios
            'DocTipo' 	=> 99, // Tipo de documento del comprador (80 CUIT, 96 DNI, 99 CONSUMIDOR FINAL)
            'DocNro' 	=> 0,  // Número de documento del comprador (0 consumidor final)
            'CbteDesde' 	=> 1,  // Número de comprobante o numero del primer comprobante en caso de ser mas de uno
            'CbteHasta' 	=> 1,  // Número de comprobante o numero del último comprobante en caso de ser mas de uno
            'CbteFch' 	=> intval(date('Ymd')), // (Opcional) Fecha del comprobante (yyyymmdd) o fecha actual si es nulo
            'ImpTotal' 	=> 100, // Importe total del comprobante
            'ImpTotConc' 	=> 0,   // Importe neto no gravado
            'ImpNeto' 	=> 100, // Importe neto gravado
            'ImpOpEx' 	=> 0,   // Importe exento de IVA
            'ImpIVA' 	=> 0,  //Importe total de IVA, valor 0 si es comprobante tipo c
            'ImpTrib' 	=> 0,   //Importe total de tributos
            'MonId' 	=> 'PES', //Tipo de moneda usada en el comprobante (ver tipos disponibles)('PES' para pesos argentinos)
            'MonCotiz' 	=> 1,     // Cotización de la moneda usada (1 para pesos argentinos)
        );

        $factura_afip = $afip->ElectronicBilling->CreateNextVoucher($data, true);
        print_r($factura_afip);
        $last_voucher = $afip->ElectronicBilling->GetLastVoucher(1,11); //Devuelve el número del último comprobante creado para el punto de venta 1 y el tipo de comprobante 6 (Factura B)
        print_r($last_voucher);
    }
}
