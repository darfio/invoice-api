<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\Plan;

class SyncInvoiceService
{
    private $invoice_before_days = 10;
    private $errors = [];
    private $vendor_paysera = 'PAYSERA';
    private $vendor_paysera_series = 'PS'; //get from settings

    public function getErrors(){
        return $this->errors;
    }

    public function completeInvoice(Invoice $item, Carbon $paid_at, $paid_by_hand=null)
    {
        if(isset($paid_at) && $paid_at->ne($item->paid_at)){    
            $item->paid_at = $paid_at;
            $item->paid_by_hand = $paid_by_hand;
            $item->update();
            //event(new PaidInvoice())
        }

        if($item->paid_at && !isset($item->invoice_nr)){
            $tenant = Tenant::find($item->tenant_id);

            //--- invoice nr
            $item->invoice_nr = Invoice::where('series', $item->series)->max('invoice_nr')+1;
            $item->update();
            //event(new InvoiceNrCreated())
            //------------------------------------------

            if($tenant && $tenant->payment == 'PAYSERA'){
                $this->updateInvoicePayerFromTenant($item, $tenant); 
                $this->updateTenantValidUntil($tenant);
            }

            //event(new CompletedInvoice())

            return true;
        }

        return false;
    }

    //add month or year
    public function updateTenantValidUntil($tenant){
        $is_yearly = isset($tenant->is_yearly) && $tenant->is_yearly;
                
        if(isset($tenant->valid_until)){
            if($is_yearly){
                $valid_until = (new Carbon($tenant->valid_until))->addYear();
            }
            else{
                $valid_until = (new Carbon($tenant->valid_until))->addMonth();
            }
        }
        else{
            if($is_yearly){
                $valid_until = Carbon::now()->addYear();
            }
            else{
                $valid_until = Carbon::now()->addMonth();
            }
        }

        // dd($tenant);
        $tenant->valid_until = $valid_until ?? null;
        return $tenant->update();
    }

    private function updateInvoicePayerFromTenant($item, $tenant){
        $item->email = $tenant->p_email;
        $item->phone = $tenant->p_phone;
        $item->address = $tenant->p_address;
        $item->company = $tenant->p_company;
        $item->company_code = $tenant->p_company_code;
        $item->company_tax = $tenant->p_company_tax;
        $item->personal_code = $tenant->p_personal_code;
        $item->email = $tenant->p_email;
        $item->update();
    }

    public function syncPayseraInvoices($tenant)
    {
        $description = $tenant->tenant_info->plan_description;
        if(!isset($description)){
            $plan = Plan::find($tenant->plan_id);
            if($plan){
                $description = $plan->title;
            }
        }

        $can_generate = $this->can_generate_paysera_invoice($tenant);

        if( $can_generate )
        {   
            // $vendor_id = $this->get_max_paysera_id();

            $invoice_model = Invoice::create([
                'tenant_id' => $tenant->id,
                'owner_company' => $tenant->company,
                //'vendor_id' => $vendor_id,
                'vendor' => $this->vendor_paysera,
                'series' => $this->vendor_paysera_series,
                'description' => $description,
                'total' => $this->get_price($tenant),
            ]);
        }

    }

    private function get_price($tenant)
    {
        $price = $tenant->price ?? null;

        if(!isset($price)){
            $price = $tenant->tenant_info->price ?? null;
        }
        
        if(!isset($price))
            return null;

        $price = $price * 100;

        if(isset($tenant->is_yearly) && $tenant->is_yearly){
            $price *= 12;
        }
        return $price;
    }

    public function can_generate_paysera_invoice($tenant)
    {
        $this->vendor_paysera_series = get_settings($tenant->company.'_paysera_series', 'GENERAL');
        if(empty($this->vendor_paysera_series)){
            $error_msg = 'invoiceApi: Setting not found: '.$tenant->company.'_paysera_series';
            $this->errors[] = $error_msg; 
            return false;
        }

        $now = Carbon::now();
        $valid_until = new Carbon($tenant->valid_until);
        $diff_in_days = $now->diffInDays($valid_until);
        $price = $this->get_price($tenant);

        $valid_until_ended = false;
        if($now > $valid_until)
            $valid_until_ended = true;
        
        if(!isset($price) || $price == 0){
            $error_msg = 'invoiceApi: Price not set';
            $this->errors[] = $error_msg; 
            return false;
        }

        if($valid_until_ended || $diff_in_days < $this->invoice_before_days){
            $paid_invoice = Invoice::where('tenant_id', $tenant->id)
                        ->where('vendor', $this->vendor_paysera)
                        ->where('paid_at', null)->get();

            $paid_invoice_count = $paid_invoice->count();

            //--- visos saskaitos sumoketos ir laikas generuoti nauja
            if($paid_invoice_count == 0){
                return true;
            }
            //-------------------------------------------------------
        }

        $error_msg = 'invoiceApi: no invoices generated';
        $this->errors[] = $error_msg;
        return false;
    }


    public function validateTenant($tenant){
        if(!$tenant){
            $error = [
                // 'message' => 'Tenant not found', 
                'errors' => [
                    'Tenant not found'
                ]
            ];
            return response($error, 404);
        }

        if(!isset($tenant->customer_id) || empty($tenant->customer_id)){
            $error = [
                // 'message' => 'customer_id not found', 
                'errors' => [
                    'customer_id not found'
                ]
            ];
            return response($error, 404);
        }

        $price = $this->get_price($tenant);
        if(!isset($price)){
            $error = [
                // 'message' => 'price not set', 
                'errors' => [
                    'price not set'
                ]
            ];
            return response($error, 404);
        }

        if($price == 0){
            $error = [
                // 'message' => 'price not set', 
                'errors' => [
                    'price is 0'
                ]
            ];
            return response($error, 404);
        }

        if(!isset($tenant->tenant_info) || !isset($tenant->tenant_info->plan)){
            $error = [
                // 'message' => 'plan not set', 
                'errors' => [
                    'plan not set'
                ]
            ];
            return response($error, 404);
        }

        $can_generate = $this->can_generate_paysera_invoice($tenant);
        if(!$can_generate){
            $errors = $this->getErrors();
            $error = [
                // 'message' => $errors[0] ?? 'error', 
                'errors' => $this->getErrors(),
            ];
            return response($error, 404);
        }

        return true;
    }
}