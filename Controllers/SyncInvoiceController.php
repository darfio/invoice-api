<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SyncInvoiceService;
use App\Models\Tenant\Tenant;
use App\Models\Invoice;

class SyncInvoiceController extends Controller
{
    private $sync_service;
    
    public function __construct()
    {
       $this->sync_service = new SyncInvoiceService;
    }

    public function sync(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required',
        ]);

        $tenant = Tenant::find($request->tenant_id);

        $valid = $this->sync_service->validateTenant($tenant);
        if($valid !== true)
            return $valid;

        if($tenant->is_paysera){
            $this->sync_service->syncPayseraInvoices($tenant);
        }
        // elseif($tenant->is_stripe){
        //     $this->sync_service->syncStripeInvoices($tenant);
        // }

        $invoices = Invoice::where('tenant_id', $tenant->id)->get();

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'is_paysera' => $tenant->is_paysera,
            'invoices' => $invoices,
            //
            // 'errors' => $this->sync_service->getErrors(),
            // 'message' => $this->sync_service->getErrorMessage(),
        ];
    }

}
