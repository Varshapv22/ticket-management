<?php

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    /** Only the agent the ticket is assigned to may change its status. */
    public function authorize(): bool
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return $this->user()->isAgent()
            && $ticket->assigned_agent_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                Ticket::STATUS_OPEN,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_RESOLVED,
            ])],
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'You can only update tickets assigned to you.');
    }
}
