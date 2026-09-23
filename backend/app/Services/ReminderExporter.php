<?php

namespace App\Services;

use App\Models\Reminder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the "Export Excel" .xlsx file: every reminder matching the given
 * filters (the same ones the list/table use), with no pagination — if no
 * filters are given, every reminder is included.
 */
class ReminderExporter
{
    private const HEADINGS = [
        'ID', 'Reminder Type', 'Customer / Company', 'Phone',
        'Product Name', 'Product Category', 'Quantity', 'Price', 'Website Link',
        'Booking / Tour Name', 'Property / Project Name', 'Location',
        'Scheduled / Due Date', 'Reminder Date', 'Assigned To', 'Status', 'Days Overdue',
        'Notes', 'Created At',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(array $filters): StreamedResponse
    {
        $spreadsheet = $this->build($filters);
        $filename = 'lizy-reminders-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function build(array $filters): Spreadsheet
    {
        $reminders = Reminder::query()
            ->with(['assignee:id,name', 'category:id,name'])
            ->filter($filters)
            ->orderByDesc('id')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reminders');

        $sheet->fromArray(self::HEADINGS, null, 'A1');
        $sheet->getStyle('A1:'.$this->lastColumn().'1')->getFont()->setBold(true);

        $row = 2;
        foreach ($reminders as $reminder) {
            $sheet->fromArray($this->row($reminder), null, "A{$row}");
            $row++;
        }

        foreach (range('A', $this->lastColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * @return array<int, mixed>
     */
    private function row(Reminder $reminder): array
    {
        return [
            $reminder->id,
            $reminder->reminder_type,
            $reminder->customer_name,
            $reminder->phone,
            $reminder->product_name,
            $reminder->category?->name,
            $reminder->quantity,
            $reminder->price !== null ? (float) $reminder->price : null,
            $reminder->website_link,
            $reminder->booking_name,
            $reminder->property_name,
            $reminder->location,
            $reminder->scheduled_date->toDateString(),
            $reminder->reminder_date->toDateString(),
            $reminder->assignee?->name ?? 'Unassigned',
            $reminder->state(),
            $reminder->daysOverdue(),
            $reminder->notes,
            $reminder->created_at->toDateTimeString(),
        ];
    }

    private function lastColumn(): string
    {
        return chr(ord('A') + count(self::HEADINGS) - 1);
    }
}
