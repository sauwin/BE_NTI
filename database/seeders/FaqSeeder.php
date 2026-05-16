<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FaqItem;
use App\Models\FaqItemTranslation;

class FaqSeeder extends Seeder
{
    private array $data = [
        [
            'question' => 'Who can apply to Program A?',
            'answer' => 'Any student of UKF Nitra can apply.'
        ],
        [
            'question' => 'What documents are required for Program A?',
            'answer' => 'You need to upload 6 documents.'
        ],
        [
            'question' => 'How often can I apply?',
            'answer' => 'NTI opens evaluation rounds quarterly. You can apply in any open round. The committee will notify you of the decision by email.'
        ],
        [
            'question' => 'What is Program B?',
            'answer' => 'Program B connects student teams with real companies that have software development tasks. You apply to a specific company project, get selected by the committee together with the company, and work on the project with an NTI mentor.'
        ],
        [
            'question' => 'Is there financial compensation in Program B?',
            'answer' => 'Yes. Each company sets a budget for the student team. The compensation is defined in the project brief before you apply.'
        ],
        [
            'question' => 'Can one student be in multiple teams?',
            'answer' => 'No. A student can only be an active member of one team at a time per program.'
        ],
        [
            'question' => 'What happens after my application is approved?',
            'answer' => 'You will be onboarded — an NTI mentor is assigned, project milestones are set, and the active phase begins. You will track progress and upload deliverables through the platform.'
        ],
        [
            'question' => 'How is the application evaluated?',
            'answer' => 'Each application is reviewed by multiple committee members independently. They score it based on weighted criteria defined per call. The final score is a weighted average.'
        ],
    ];

    public function run(): void
    {
        foreach ($this->data as $index => $row) {
            $item = FaqItem::create([
                'page_context' => 'general',
                'order_position' => $index,
                'is_active' => true,
            ]);

            FaqItemTranslation::create([
                'faq_item_id' => $item->id,
                'language' => 'en',
                'question' => $row['question'],
                'answer' => $row['answer'],
            ]);
        }
    }
}