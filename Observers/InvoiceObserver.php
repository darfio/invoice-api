<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceObserver
{

    public function creating(Invoice $item)
    {
        $item->token = Str::random(64);
    }

    public function updating(Invoice $item)
    {
        //
    }
}
