<?php

namespace App\Exports;

use App\Models\BulkNotificationCampaign;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class BulkNotificationCampaignsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = BulkNotificationCampaign::query()
            ->with('sender:id,first_name,last_name,email');

        if (!empty($this->filters['subject'])) {
            $query->where('subject', 'like', '%' . $this->filters['subject'] . '%');
        }

        if (!empty($this->filters['recipient_group'])) {
            $query->where('recipient_group', $this->filters['recipient_group']);
        }

        if (!empty($this->filters['sender_id'])) {
            $query->where('sender_id', $this->filters['sender_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->where('created_at', '>=', $this->filters['date_from'] . ' 00:00:00');
        }

        if (!empty($this->filters['date_to'])) {
            $query->where('created_at', '<=', $this->filters['date_to'] . ' 23:59:59');
        }

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Sent Date',
            'Sender Name',
            'Sender Email',
            'Recipient Group',
            'Subject',
            'Message Core Content',
            'Total Queued Recipients',
        ];
    }

    /**
     * @param BulkNotificationCampaign $campaign
     */
    public function map($campaign): array
    {
        $senderName = $campaign->sender 
            ? trim(($campaign->sender->first_name ?? '') . ' ' . ($campaign->sender->last_name ?? ''))
            : '—';

        return [
            $campaign->id,
            $campaign->created_at ? $campaign->created_at->format('Y-m-d H:i:s') : '—',
            $senderName ?: '—',
            $campaign->sender->email ?? '—',
            strtoupper($campaign->recipient_group),
            $campaign->subject,
            $campaign->message,
            $campaign->total_recipients . ' users',
        ];
    }
}