<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;


class PDFController extends Controller
{
    public static function generateAndSaveInvoicePdf(Invoice $invoice): string
    {
        $pdf = PDF::loadView('invoice_pdf', compact('invoice'));

        $pdfPath = 'invoices/invoice-' . $invoice->invoice_number . '.pdf';

        // Save the PDF to storage/app/public/invoices/
        Storage::disk('public')->put($pdfPath, $pdf->output());

        return $pdfPath;
    }

}
