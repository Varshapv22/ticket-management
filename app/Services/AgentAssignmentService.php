<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;

class AgentAssignmentService
{
    /**
     * Pick the agent with the fewest active (open / in_progress) tickets.
     * Ties are broken by the earliest created agent (lowest id).
     */
    public function findAvailableAgent(): ?User
    {
        return User::query()
            ->agents()
            ->withCount([
                'assignedTickets as active_tickets_count' => fn ($query) => $query->active(),
            ])
            ->orderBy('active_tickets_count')
            ->orderBy('id')
            ->first();
    }

   
    public function assign(Ticket $ticket): ?User
    {
        $agent = $this->findAvailableAgent();

        if ($agent) {
            $ticket->assigned_agent_id = $agent->id;
            $ticket->save();
        }

        return $agent;
    }
}
