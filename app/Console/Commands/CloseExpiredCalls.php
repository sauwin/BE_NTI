<?php
namespace App\Console\Commands;
use App\Models\Call;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
class CloseExpiredCalls extends Command
{
    public $signature = 'calls:closeExpired';
    public $description = 'Automatically close calls with passed deadlines';
    public function handle()
    {
        $now = now();
        $closed = Call::where('status', 'open')
            ->where('deadline_at', '<', $now)
            ->update(['status' => 'closed']);
        if ($closed > 0) {
            Log::info("Closed {$closed} expired calls");
            $this->info("✓ Closed {$closed} expired calls");
        } else {
            $this->info('No expired calls to close');
        }
        return 0;
    }
}
