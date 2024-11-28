<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\InvoiceMeta;
use App\Models\Tenant\Tenant;

class Invoice extends Model
{
    use SoftDeletes;
    
    public $timestamps = true;
    protected $casts = [
        'created_at'  => 'datetime:Y-m-d H:i:s',
        'updated_at'  => 'datetime:Y-m-d H:i:s',
        'paid_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $fillable = [
        'tenant_id',
        'owner_company',
        'vendor_id',
        'invoice_nr',
        'series',
        'vendor',
        'description',
        'email',
        'phone',
        'address',
        'company',
        'company_code',
        'company_tax',
        'personal_code',
        'total',
        'token',
        'paid_by_hand',
        'paid_at',
        'created_at',
    ];

    protected $appends = ['full_invoice_nr', 'full_pre_invoice_nr', 'pre_invoice_url', 'invoice_url', 'paysera_checkout_url'];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getPayseraCheckoutUrlAttribute(){
        $checkout_url = config('services.paysera.checkout_url');
        return $checkout_url .'/'. $this->id;
    }

    public function getFullPreInvoiceNrAttribute(){
        $series = !empty($this->series) ? $this->series.'-' : '';
        return $this->id;
    }

    public function getFullInvoiceNrAttribute(){
        if(isset($this->invoice_nr) && $this->invoice_nr!==''){
            $series = !empty($this->series) ? $this->series.'-' : '';
            return $series . $this->invoice_nr;
        }
            
        return null;
    }

    public function getPreInvoiceUrlAttribute(){
        if(isset($this->tenant_id) && isset($this->token))
            //if(!$this->paid){
                return route('pre.invoices.show', [
                    //'tenant_id' => $this->tenant_id,
                    'invoice_id' => $this->id,
                    'token' => $this->token,
                ]);
            //}
        return null;
    }

    public function getInvoiceUrlAttribute(){
        if(isset($this->tenant_id) && isset($this->token))
            if(isset($this->invoice_nr)){
                return route('invoices.show', [
                    //'tenant_id' => $this->tenant_id,
                    'invoice_id' => $this->id,
                    'token' => $this->token,
                ]);
            }
        return null;
    }

    public function getTotal(){
        if(!is_numeric($this->total))
            return null;

        $total = $this->total / 100;

        return $total;
    }

    public function metas(){
        return $this->hasMany(InvoiceMeta::class);
    }

    public function tenant(){
        return $this->belongsTo(Tenant::class);
    }

    public function getMeta($name, $section=null){
        if(!$section)
            return InvoiceMeta::where('invoice_id', $this->id)
                            ->where('name', $name)->first();
        return InvoiceMeta::where('invoice_id', $this->id)
                        ->where('name', $name)
                        ->where('section', $section)
                        ->first();
    }

    public function setMeta($name, $section=null, $value=null){
        $meta = null;
        if(!$section){
            $meta = InvoiceMeta::where('invoice_id', $this->id)
                            ->where('name', $name)->first();
        }
        else{
            $meta = InvoiceMeta::where('invoice_id', $this->id)
            ->where('name', $name)
            ->where('section', $section)
            ->first();
        }
        if($meta){
            $meta->value = $value;
            $meta->update();
        }
        else{
            $meta = new InvoiceMeta;
            $meta->invoice_id = $this->id;
            $meta->name = $name;
            $meta->section = $section;
            $meta->value = $value;
            $meta->save();
        }
    }
}
