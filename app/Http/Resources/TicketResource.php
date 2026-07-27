<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'ticket_number' => $this->ticket_number,
            'title'         => $this->title,
            'description'   => $this->description,
            'priority'      => $this->priority,
            'status'        => $this->status,
            'resolved_at'   => $this->resolved_at?->toDateTimeString(),
            'created_at'    => $this->created_at?->toDateTimeString(),
            'created_by'    => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'assigned_agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id'    => $this->agent->id,
                'name'  => $this->agent->name,
                'email' => $this->agent->email,
            ] : null),
        ];
    }
}
