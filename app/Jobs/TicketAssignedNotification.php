<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TicketAssignedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Ticket $ticket
    ) {
    }

    public function handle(): void
    {
        $agentName = $this->ticket->agent?->name ?? 'Unassigned';

        Log::info("Ticket {$this->ticket->ticket_number} has been assigned to Agent {$agentName}.");
    }
}
