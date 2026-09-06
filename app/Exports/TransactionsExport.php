<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return Transaction::with([
            'user', 
            'eventItem.providerService.user', 
            'eventItem.service'
        ])->latest()->get();
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Customer Name',
            'Provider Name',
            'Service Name',
            'Total Amount',
            'Admin Commission',
            'Provider Amount',
            'Payment Method',
            'Status',
            'Created At'
        ];
    }

    public function map($transaction): array
    {
        $eventItem = $transaction->eventItem;

        $serviceName = $transaction->service_name 
            ?? $eventItem->providerService->title 
            ?? $eventItem->providerService->name 
            ?? $eventItem->service->name 
            ?? $eventItem->title 
            ?? 'N/A';

        $providerName = $eventItem->providerService->user->username 
            ?? $eventItem->user->username 
            ?? 'N/A';

        return [
            $transaction->id,
            $transaction->user->username ?? 'N/A',
            $providerName,
            $serviceName,
            $transaction->amount ?? '0.00',
            $transaction->admin_commission ?? '0.00',
            $transaction->provider_amount ?? '0.00',
            strtoupper($transaction->payment_method ?? 'N/A'),
            $transaction->status ?? 'completed',
            $transaction->created_at ? $transaction->created_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // جعل اتجاه الصفحة أفقياً لتتسع لكافة الأعمدة
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        return [
            // جعل ترويسة الجدول عريضة ومظللة
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
