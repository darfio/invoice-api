<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::namespace('Api')->group(function(){
    Route::post('/tokens/create', 'TokenController@store');
    Route::post('/tokens/get', 'TokenController@index');

    Route::middleware('auth:sanctum')->group(function(){
        Route::post(
            '/sync/invoices', 'SyncInvoiceController@sync'
        )->name("sync.invoices");

        Route::put(
            '/invoices/vendors/{invoice_id}/paid', 'InvoiceController@paid'
        )->name("invoices.vendors.paid");
        Route::patch(
            '/invoices/{id}/restore', 'InvoiceController@restore'
        )->name("invoices.restore");
        Route::resource('invoices', 'InvoiceController');
    });
});
