<?php
namespace Crater\Http\Controllers;

use Illuminate\Http\Request;
use Crater\CompanySetting;
use Crater\Company;
use Illuminate\Support\Collection;
use Crater\Currency;
use Crater\InvoiceTemplate;
use Crater\Http\Requests;
use Crater\Invoice;
use Crater\InvoiceItem;
use Carbon\Carbon;
use Crater\Item;
use Crater\Mail\invoicePdf;
use function MongoDB\BSON\toJSON;
use Illuminate\Support\Facades\Log;
use Crater\User;
use Mailgun\Mailgun;
use PDF;
use Validator;
use Crater\TaxType;
use Crater\Tax;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $invoices = Invoice::with(['items', 'user', 'invoiceTemplate', 'taxes'])
            ->join('users', 'users.id', '=', 'invoices.user_id')
            ->applyFilters($request->only([
                'status',
                'paid_status',
                'customer_id',
                'invoice_number',
                'from_date',
                'to_date',
                'orderByField',
                'orderBy',
                'search',
            ]))
            ->whereCompany($request->header('company'))
            ->select('invoices.*', 'users.name')
            ->latest()
            ->paginate($limit);

        return response()->json([
            'invoices' => $invoices,
            'invoiceTotalCount' => Invoice::count()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $tax_per_item = CompanySetting::getSetting('tax_per_item', $request->header('company'));
        $discount_per_item = CompanySetting::getSetting('discount_per_item', $request->header('company'));
        $invoice_prefix = CompanySetting::getSetting('invoice_prefix', $request->header('company'));
        $invoice_num_auto_generate = CompanySetting::getSetting('invoice_auto_generate', $request->header('company'));

        $nextInvoiceNumberAttribute = null;
        $nextInvoiceNumber = Invoice::getNextInvoiceNumber($invoice_prefix);

        if ($invoice_num_auto_generate == "YES") {
            $nextInvoiceNumberAttribute = $nextInvoiceNumber;
        }

        return response()->json([
            'nextInvoiceNumberAttribute' => $nextInvoiceNumberAttribute,
            'nextInvoiceNumber' => $invoice_prefix.'-'.$nextInvoiceNumber,
            'items' => Item::with('taxes')->whereCompany($request->header('company'))->get(),
            'invoiceTemplates' => InvoiceTemplate::all(),
            'tax_per_item' => $tax_per_item,
            'discount_per_item' => $discount_per_item,
            'invoice_prefix' => $invoice_prefix
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Requests\InvoicesRequest $request)
    {
        $invoice_number = explode("-",$request->invoice_number);
        $number_attributes['invoice_number'] = $invoice_number[0].'-'.sprintf('%06d', intval($invoice_number[1]));

        Validator::make($number_attributes, [
            'invoice_number' => 'required|unique:invoices,invoice_number,'
        ])->validate();

        $invoice_date = Carbon::createFromFormat('d/m/Y', $request->invoice_date);
        $due_date = Carbon::createFromFormat('d/m/Y', $request->due_date);
        $cae_expiration_date = Carbon::createFromFormat('d/m/Y', $request->cae_expiration_date);
        $status = Invoice::STATUS_DRAFT;

        $tax_per_item = CompanySetting::getSetting('tax_per_item', $request->header('company')) ?? 'NO';
        $discount_per_item = CompanySetting::getSetting('discount_per_item', $request->header('company')) ?? 'NO';

        if ($request->has('invoiceSend')) {
            $status = Invoice::STATUS_SENT;
        }

        $company = Company::find($request->company_id);

        // Crear Factura
        $produccion = env('AFIP_INVOICE_PRODUCTION', false);
        $certificado = $produccion ? 'certificado-produccion.crt' : 'certificado-test.crt';
        $afip = new \Afip(
            array(
            'CUIT' => 23316829759,
            'res_folder' => storage_path('app/afip/certificados'),
            'ta_folder' => storage_path('app/afip/tokens'),
            'cert' => $certificado,
            'key' => 'pdrappo-clave-afip.key',
            'production' => $produccion
            )
        );

        $data = array(
            'CantReg' 	=> 1,  // Cantidad de comprobantes a registrar
            'PtoVta' 	=> env('AFIP_INVOICE_BUSINESS_POINT', 1),  // Punto de venta
            'CbteTipo' 	=> 11,  // Tipo de comprobante (ver tipos disponibles), Factura C
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
            'Iva' 		=> array( // (Opcional) Alícuotas asociadas al comprobante
                array(
                    'Id' 		=> 5, // Id del tipo de IVA (5 para 21%)(ver tipos disponibles)
                    'BaseImp' 	=> 100, // Base imponible
                    'Importe' 	=> 21 // Importe
                )
            ),
        );

        $factura_afip = $afip->ElectronicBilling->CreateVoucher($data, true);

        $invoice = Invoice::create([
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'invoice_code_id' => $request->invoice_code_id,
            'business_point_number' => 2,
            'cae_number' => 121231231,
            'cae_expiration_date' => $cae_expiration_date,
            'invoice_number' => $number_attributes['invoice_number'],
            'reference_number' => $request->reference_number,
            'user_id' => $request->user_id,
            'company_id' => $request->header('company'),
            'invoice_template_id' => $request->invoice_template_id,
            'status' => $status,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $request->sub_total,
            'discount' => $request->discount,
            'discount_type' => $request->discount_type,
            'discount_val' => $request->discount_val,
            'total' => $request->total,
            'due_amount' => $request->total,
            'tax_per_item' => $tax_per_item,
            'discount_per_item' => $discount_per_item,
            'tax' => $request->tax,
            'notes' => $request->notes,
            'unique_hash' => str_random(60)
        ]);

        $invoiceItems = $request->items;

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['company_id'] = $request->header('company');
            $item = $invoice->items()->create($invoiceItem);

            if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                foreach ($invoiceItem['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');
                    if (gettype($tax['amount']) !== "NULL") {
                        $item->taxes()->create($tax);
                    }
                }
            }
        }

        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                $tax['company_id'] = $request->header('company');

                if (gettype($tax['amount']) !== "NULL") {
                    $invoice->taxes()->create($tax);
                }
            }
        }

        if ($request->has('invoiceSend')) {
            $data['invoice'] = Invoice::findOrFail($invoice->id)->toArray();
            $data['user'] = User::find($request->user_id)->toArray();
            $data['company'] = Company::find($invoice->company_id);

            $notificationEmail = CompanySetting::getSetting(
                'notification_email',
                $request->header('company')
            );

            $email = $data['user']['email'];

            if (!$email) {
                return response()->json([
                    'error' => 'user_email_does_not_exist'
                ]);
            }

            if (!$notificationEmail) {
                return response()->json([
                    'error' => 'notification_email_does_not_exist'
                ]);
            }

            \Mail::to($email)->send(new invoicePdf($data, $notificationEmail));
        }

        $invoice = Invoice::with(['items', 'user', 'invoiceTemplate', 'taxes'])->find($invoice->id);

        return response()->json([
            'url' => url('/invoices/pdf/'.$invoice->unique_hash),
            'invoice' => $invoice,
            'afip' => $factura_afip
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $invoice = Invoice::with([
            'items',
            'items.taxes',
            'user',
            'invoiceTemplate',
            'taxes.taxType'
        ])->find($id);

        $siteData = [
            'invoice' => $invoice,
            'shareable_link' => url('/invoices/pdf/' . $invoice->unique_hash)
        ];

        return response()->json($siteData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request,$id)
    {
        $invoice = Invoice::with([
            'items',
            'items.taxes',
            'user',
            'invoiceTemplate',
            'taxes.taxType'
        ])->find($id);

        return response()->json([
            'nextInvoiceNumber' => $invoice->getInvoiceNumAttribute(),
            'invoice' => $invoice,
            'invoiceTemplates' => InvoiceTemplate::all(),
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'shareable_link' => url('/invoices/pdf/'.$invoice->unique_hash),
            'invoice_prefix' => $invoice->getInvoicePrefixAttribute()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Requests\InvoicesRequest $request, $id)
    {
        $invoice_number = explode("-",$request->invoice_number);
        $number_attributes['invoice_number'] = $invoice_number[0].'-'.sprintf('%06d', intval($invoice_number[1]));
        $cae_expiration_date = Carbon::createFromFormat('d/m/Y', $request->cae_expiration_date);

        Validator::make($number_attributes, [
            'invoice_number' => 'required|unique:invoices,invoice_number'.','.$id
        ])->validate();

        $invoice_date = Carbon::createFromFormat('d/m/Y', $request->invoice_date);
        $due_date = Carbon::createFromFormat('d/m/Y', $request->due_date);

        $invoice = Invoice::find($id);
        $oldAmount = $invoice->total;

        if ($oldAmount != $request->total) {
            $oldAmount = (int)round($request->total) - (int)$oldAmount;
        } else {
            $oldAmount = 0;
        }

        $invoice->due_amount = ($invoice->due_amount + $oldAmount);

        if ($invoice->due_amount == 0 && $invoice->paid_status != Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_COMPLETED;
            $invoice->paid_status = Invoice::STATUS_PAID;
        } elseif ($invoice->due_amount < 0 && $invoice->paid_status != Invoice::STATUS_UNPAID) {
            return response()->json([
                'error' => 'invalid_due_amount'
            ]);
        } elseif ($invoice->due_amount != 0 && $invoice->paid_status == Invoice::STATUS_PAID) {
            $invoice->status = $invoice->getPreviousStatus();
            $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
        }

        $invoice->invoice_date = $invoice_date;
        $invoice->due_date = $due_date;
        $invoice->business_point_number = 2;
        $invoice->cae_number = $request->cae_number;
        $invoice->cae_expiration_date = $request->cae_expiration_date;
        $invoice->invoice_type_id = $request->invoice_code_id;
        $invoice->invoice_type = Invoice::TYPE_FB;
        $invoice->invoice_number =  $number_attributes['invoice_number'];
        $invoice->reference_number = $request->reference_number;
        $invoice->user_id = $request->user_id;
        $invoice->invoice_template_id = $request->invoice_template_id;
        $invoice->sub_total = $request->sub_total;
        $invoice->total = $request->total;
        $invoice->discount = $request->discount;
        $invoice->discount_type = $request->discount_type;
        $invoice->discount_val = $request->discount_val;
        $invoice->tax = $request->tax;
        $invoice->notes = $request->notes;
        $invoice->save();

        $oldItems = $invoice->items->toArray();
        $oldTaxes = $invoice->taxes->toArray();
        $invoiceItems = $request->items;

        foreach ($oldItems as $oldItem) {
            InvoiceItem::destroy($oldItem['id']);
        }

        foreach ($oldTaxes as $oldTax) {
            Tax::destroy($oldTax['id']);
        }
        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['company_id'] = $request->header('company');
            $item = $invoice->items()->create($invoiceItem);

            if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                foreach ($invoiceItem['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');
                    if (gettype($tax['amount']) !== "NULL") {
                        $item->taxes()->create($tax);
                    }
                }
            }
        }

        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                $tax['company_id'] = $request->header('company');

                if (gettype($tax['amount']) !== "NULL") {
                    $invoice->taxes()->create($tax);
                }
            }
        }

        $invoice = Invoice::with(['items', 'user', 'invoiceTemplate', 'taxes'])->find($invoice->id);

        return response()->json([
            'url' => url('/invoices/pdf/' . $invoice->unique_hash),
            'invoice' => $invoice,
            'success' => true
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $invoice = Invoice::find($id);

        if ($invoice->payments()->exists() && $invoice->payments()->count() > 0) {
            return response()->json([
                'error' => 'payment_attached'
            ]);
        }

        $invoice = Invoice::destroy($id);

        return response()->json([
            'success' => true
        ]);
    }

    public function delete(Request $request)
    {
        foreach ($request->id as $id) {
            $invoice = Invoice::find($id);

            if ($invoice->payments()->exists() && $invoice->payments()->count() > 0) {
                return response()->json([
                    'error' => 'payment_attached'
                ]);
            }
        }

        $invoice = Invoice::destroy($request->id);

        return response()->json([
            'success' => true
        ]);
    }



     /**
     * Mail a specific invoice to the correponding cusitomer's email address.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendInvoice(Request $request)
    {
        $invoice = Invoice::findOrFail($request->id);

        $data['invoice'] = $invoice->toArray();
        $userId = $data['invoice']['user_id'];
        $data['user'] = User::find($userId)->toArray();
        $data['company'] = Company::find($invoice->company_id);
        $email = $data['user']['email'];
        $notificationEmail = CompanySetting::getSetting(
            'notification_email',
            $request->header('company')
        );

        if (!$email) {
            return response()->json([
                'error' => 'user_email_does_not_exist'
            ]);
        }

        if (!$notificationEmail) {
            return response()->json([
                'error' => 'notification_email_does_not_exist'
            ]);
        }

        \Mail::to($email)->send(new invoicePdf($data, $notificationEmail));

        if ($invoice->status == Invoice::STATUS_DRAFT) {
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        }


        return response()->json([
            'success' => true
        ]);
    }


     /**
     * Mark a specific invoice as sent.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsSent(Request $request)
    {
        $invoice = Invoice::findOrFail($request->id);
        $invoice->status = Invoice::STATUS_SENT;
        $invoice->sent = true;
        $invoice->save();

        return response()->json([
            'success' => true
        ]);
    }


     /**
     * Mark a specific invoice as paid.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsPaid(Request $request)
    {
        $invoice = Invoice::findOrFail($request->id);
        $invoice->status = Invoice::STATUS_COMPLETED;
        $invoice->paid_status = Invoice::STATUS_PAID;
        $invoice->due_amount = 0;
        $invoice->save();

        return response()->json([
            'success' => true
        ]);
    }


     /**
     * Retrive a specified user's unpaid invoices from storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomersUnpaidInvoices(Request $request, $id)
    {
        $invoices = Invoice::where('paid_status', '<>', Invoice::STATUS_PAID)
            ->where('user_id', $id)->where('due_amount', '>', 0)
            ->whereCompany($request->header('company'))
            ->get();

        return response()->json([
            'invoices' => $invoices
        ]);
    }

    public function cloneInvoice(Request $request)
    {
        $oldInvoice = Invoice::with([
            'items.taxes',
            'user',
            'invoiceTemplate',
            'taxes.taxType'
        ])
        ->find($request->id);

        $date = Carbon::now();
        $invoice_prefix = CompanySetting::getSetting(
            'invoice_prefix',
            $request->header('company')
        );
        $tax_per_item = CompanySetting::getSetting(
                'tax_per_item',
                $request->header('company')
            ) ? CompanySetting::getSetting(
                'tax_per_item',
                $request->header('company')
            ) : 'NO';
        $discount_per_item = CompanySetting::getSetting(
                'discount_per_item',
                $request->header('company')
            ) ? CompanySetting::getSetting(
                'discount_per_item',
                $request->header('company')
            ) : 'NO';

        $invoice = Invoice::create([
            'invoice_date' => $date,
            'due_date' => $date,
            'invoice_number' => $invoice_prefix."-".Invoice::getNextInvoiceNumber($invoice_prefix),
            'reference_number' => $oldInvoice->reference_number,
            'user_id' => $oldInvoice->user_id,
            'company_id' => $request->header('company'),
            'invoice_template_id' => 1,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $oldInvoice->sub_total,
            'discount' => $oldInvoice->discount,
            'discount_type' => $oldInvoice->discount_type,
            'discount_val' => $oldInvoice->discount_val,
            'total' => $oldInvoice->total,
            'due_amount' => $oldInvoice->total,
            'tax_per_item' => $oldInvoice->tax_per_item,
            'discount_per_item' => $oldInvoice->discount_per_item,
            'tax' => $oldInvoice->tax,
            'notes' => $oldInvoice->notes,
            'unique_hash' => str_random(60)
        ]);

        $invoiceItems = $oldInvoice->items->toArray();

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem['company_id'] = $request->header('company');
            $invoiceItem['name'] = $invoiceItem['name'];
            $item = $invoice->items()->create($invoiceItem);

            if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                foreach ($invoiceItem['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');

                    if ($tax['amount']) {
                        $item->taxes()->create($tax);
                    }
                }
            }
        }

        if ($oldInvoice->taxes) {
            foreach ($oldInvoice->taxes->toArray() as $tax) {
                $tax['company_id'] = $request->header('company');
                $invoice->taxes()->create($tax);
            }
        }

        $invoice = Invoice::with([
            'items',
            'user',
            'invoiceTemplate',
            'taxes'
        ])->find($invoice->id);

        return response()->json([
            'invoice' => $invoice
        ]);
    }

    public function generateAfipInvoice($cuit, $cliente){
        $produccion = env('AFIP_INVOICE_PRODUCTION', false);
        $certificado = $produccion ? 'certificado-produccion.crt' : 'certificado-test.crt';
        $afip = new Afip(
            array(
            'CUIT' => 23316829759,
            'res_folder' => '/home/pdrappo/code/afip.php/cert/',
            'ta_folder' => '/home/pdrappo/code/afip.php/ta/',
            'cert' => $cuit,
            'key' => 'pdrappo-clave-afip.key',
            'production' => $produccion
            )
        );

        $data = array(
            'CantReg' 	=> 1,  // Cantidad de comprobantes a registrar
            'PtoVta' 	=> env('AFIP_INVOICE_BUSINESS_POINT', 1),  // Punto de venta
            'CbteTipo' 	=> 11,  // Tipo de comprobante (ver tipos disponibles), Factura C
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
            'Iva' 		=> array( // (Opcional) Alícuotas asociadas al comprobante
                array(
                    'Id' 		=> 5, // Id del tipo de IVA (5 para 21%)(ver tipos disponibles)
                    'BaseImp' 	=> 100, // Base imponible
                    'Importe' 	=> 21 // Importe
                )
            ),
        );

        return $afip->ElectronicBilling->CreateVoucher($data, true);
        //$res['CAE']; //CAE asignado el comprobante
        //$res['CAEFchVto']; //Fecha de vencimiento del CAE (yyyy-mm-dd)

    }
}
