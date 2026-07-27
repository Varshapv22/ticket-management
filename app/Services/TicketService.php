<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Jobs\TicketAssignedNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        private readonly AgentAssignmentService $agentAssignment
    ) {
    }

    /** Create a ticket, auto-assign an agent and queue the notification job. */
    public function create(User $user, array $data): Ticket
    {
        $ticket = DB::transaction(function () use ($user, $data) {
            $ticket = Ticket::create([
                'ticket_number' => $this->generateTicketNumber(),
                'title'         => $data['title'],
                'description'   => $data['description'],
                'priority'      => $data['priority'] ?? Ticket::PRIORITY_MEDIUM,
                'status'        => Ticket::STATUS_OPEN,
                'user_id'       => $user->id,
            ]);

            $this->agentAssignment->assign($ticket);

            return $ticket;
        });

        if ($ticket->assigned_agent_id) {
            TicketAssignedNotification::dispatch($ticket);
        }

        return $ticket->load(['user', 'agent']);
    }

    /** Move a ticket through the workflow: open -> in_progress -> resolved. */
    public function updateStatus(Ticket $ticket, string $status): Ticket
    {
        if (! $ticket->canTransitionTo($status)) {
            throw new InvalidStatusTransitionException(
                $ticket->status,
                $status,
                $ticket->allowedTransitions()
            );
        }

        $ticket->status = $status;

        if ($status === Ticket::STATUS_RESOLVED) {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        return $ticket->load(['user', 'agent']);
    }

    /**
     * Sequential ticket number: TKT-10001, TKT-10002 ...
     *
     * lockForUpdate() serialises concurrent creates so two tickets cannot claim
     * the same number. The API exposes no delete route, so numbers are never reused.
     */
    private function generateTicketNumber(): string
    {
        $lastId = Ticket::query()->lockForUpdate()->max('id') ?? 0;

        return 'TKT-' . (10000 + $lastId + 1);
    }
}
