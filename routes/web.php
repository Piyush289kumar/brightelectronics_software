<?php

use App\Http\Controllers\PDFController;
use Illuminate\Support\Facades\Route;
use App\Models\Document;

Route::get('/', function () {
    // return view('welcome');    
    return redirect('/admin');
});



Route::get('/documents/{document}/view', function (Document $document) {
    return response()->file(storage_path('app/public/' . $document->path));
})->name('filament.resources.documents.view');

Route::get('/documents/{document}/print', function (Document $document) {
    return response()->file(storage_path('app/public/' . $document->path));
})->name('filament.resources.documents.print');



Route::get('/invoice/{id}/pdf', [PDFController::class, 'generateInvoicePdf'])->name('invoice.pdf');
