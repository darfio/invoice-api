<?php

namespace App\Services;

use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use Carbon\Carbon;

class InvoiceService
{
    CONST PVM = 21;
    CONST DEFAULT_PLAN_DESCRIPTION = 'Už el. parduotuvės nuomos paslaugas';

    public function get_seller($owner_company)
    {
        $prefix = !empty($owner_company) ? $owner_company.'_' : '';
        $seller_company = get_settings($prefix.'company', 'GENERAL');
        $seller_company_code = get_settings($prefix.'company_code', 'GENERAL');
        $seller_company_pvm = get_settings($prefix.'company_pvm', 'GENERAL');
        $seller_phone = get_settings($prefix.'phone', 'GENERAL');
        $seller_address = get_settings($prefix.'address', 'GENERAL');

        return (object)[
            'company' => $seller_company,
            'company_code' => $seller_company_code,
            'company_pvm' => $seller_company_pvm,
            'phone' => $seller_phone,
            'address' => $seller_address,
        ];
    }
    
    public function get_invoice($invoice_obj, $seller, $payer, $is_preinvoice=false)
    {
        $serial_number_index = '';
        $table_columns = 5;
        $has_vat = false;

        $seller_custom_fields = [];
        if(isset($seller->company_code))
            $seller_custom_fields[__('invoices::invoice.code')] = $seller->company_code;
        if(isset($seller->company_pvm)){
            $has_vat = true;
            $seller_custom_fields[__('invoices::invoice.vat')] = $seller->company_pvm;
        }

        $seller = new Party([
            'name'          => $seller->company ?? '',
            //'phone'         => $seller_phone ?? '',
            'custom_fields' => $seller_custom_fields,
        ]);
        

        $customer_custom_fields = [];

        if(isset($payer->company_code))
            $customer_custom_fields[__('invoices::invoice.code')] = $payer->company_code;
        if(isset($payer->company_pvm)){
            $customer_custom_fields[__('invoices::invoice.vat')] = $payer->company_pvm;
        }
            
        if(isset($payer->personal_code) && !isset($payer->company_code)){
            $customer_custom_fields[__('invoices::invoice.personal_code')] = $payer->personal_code;
        }
            
        if(isset($payer->address))
            $customer_custom_fields[__('invoices::invoice.address')] = $payer->address;

        $customer = new Party([
            'name'          => $payer->company ?? null,
            //'phone'       => $order->phone ?? '',
            //'address'       => $customer_address,
            'custom_fields' => $customer_custom_fields,
        ]);

        //------------------ Order ITEMS ----------------------------------
        $items = [];
        //--------------------- ITEM -------------------------
        $plan_title = $invoice_obj->description ?? self::DEFAULT_PLAN_DESCRIPTION;
        $price = $invoice_obj->getTotal() ?? 0;

        if($has_vat)
            $price = $this->get_price_no_tax($price, self::PVM);

        $item = (new InvoiceItem())->title($plan_title)
                                            ->pricePerUnit($price)
                                            ->quantity(1);
                                        
        if($has_vat)
            $item->taxByPercent(self::PVM);

        $items[] = $item;
        //--------------------------------------------------------

        $invoice_title = __('invoices::invoice.invoice');
        $invoice_nr = $invoice_obj->full_invoice_nr;
        $created_at = new Carbon($invoice_obj->paid_at);
        $filename = 'invoice-'.$invoice_obj->invoice_nr;
        if($is_preinvoice){
            $filename = 'preinvoice-'.$invoice_obj->id;
            $invoice_title = __('invoices::invoice.preinvoice');
            $invoice_nr = $invoice_obj->full_pre_invoice_nr;
            $created_at = new Carbon($invoice_obj->created_at);
        }
        //--------------------------------------------------------

        
        $currencySymbol = config('invoices.currency.symbol');

        $invoice = Invoice::make($invoice_title)
            //->sequence($order->number)
            //->sequence($invoice_obj->number)
            ->serialNumberFormat($invoice_nr)
            ->seller($seller)
            ->buyer($customer)
            ->date( $created_at )
            // ->taxRate(self::PVM)
            //->dateFormat('m/d/Y')
            ->currencySymbol($currencySymbol)
            ->currencyFormat('{SYMBOL}{VALUE}')
            //->currencyThousandsSeparator('.')
            //->currencyDecimalPoint(',')
            ->filename($filename)
            ->addItems($items);
            //->logo($logo_url ?? '');
            // You can additionally save generated invoice to configured disk
            //->save('public');

        $invoice->template = 'invest';
        if($has_vat)
            $invoice->template = 'default2';
        $invoice->table_columns = $table_columns;

        return $invoice;
    }

    public function get_price_no_tax($price, $tax_percent){
        
        $tax_rate = floatVal($tax_percent / 100 + 1);
        $res = floatVal($price / $tax_rate);

        return $res;
    }
}