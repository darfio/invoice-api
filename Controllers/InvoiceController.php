<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\SyncInvoiceService;
use App\Services\InvoiceService;
use App\Traits\ApiResponser;
use App\Http\Requests\InvoiceCreateRequest;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    use ApiResponser;

    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with(['metas', 'tenant'])
            ->orderByDesc('id');

        if(isset($request->tenant_id))
            $query->where('tenant_id', $request->tenant_id);

        $invoices = $query->get();

        return $this->successResponse($invoices);
    }

    public function show($id)
    {
        $item = Invoice::with(['metas', 'tenant'])->findOrFail($id);
        
        $invoice_service = new InvoiceService;
        $item->owner = $invoice_service->get_seller($item->owner_company);

        return $this->successResponse($item);
    }

    public function store(InvoiceCreateRequest $request)
    {
        $invoice = Invoice::create($request->all());
        return $this->successResponse($invoice, Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $item = Invoice::findOrFail($id);
        $item->fill($request->all());
        if($item->isClean()){
            return $this->errorResponse('At least one value must change', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $item->update();

        return $this->successResponse($item);
    }

    public function paid(Request $request, $invoice_id)
    {
        $item = Invoice::find($invoice_id);
        if(!$item)
            return $this->errorResponse("invoice not found where id={$invoice_id}", Response::HTTP_NOT_FOUND);

        $now = Carbon::now();
        $sync_service = new SyncInvoiceService;
        $paid_by_hand = $request->paid_by_hand ?? null;
        $completed = $sync_service->completeInvoice($item, $now, $paid_by_hand);

        return $this->successResponse($item); 
    }

    public function destroy($id)
    {
        $item = Invoice::findOrFail($id);
        $item->delete();
        return $this->successResponse($item); 
    }

    public function restore($id)
    {
        $item = Invoice::onlyTrashed()->findOrFail($id);
        $item->restore();
        return $this->successResponse($item); 
    }
}
