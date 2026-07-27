<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\IndexTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService
    ) {
    }

    
    public function index(IndexTicketRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tickets = Ticket::query()
            ->with(['user', 'agent'])
            ->when(
                $user->isAgent(),
                fn ($query) => $query->where('assigned_agent_id', $user->id),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->filter($request->validated())
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data'    => new TicketResource($ticket),
        ], 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorizeAccess($ticket);

        return response()->json([
            'success' => true,
            'data'    => new TicketResource($ticket->load(['user', 'agent'])),
        ]);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket = $this->ticketService->updateStatus($ticket, $request->status);

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully.',
            'data'    => new TicketResource($ticket),
        ]);
    }

    private function authorizeAccess(Ticket $ticket): void
    {
        $user = request()->user();

        $allowed = $user->isAgent()
            ? $ticket->assigned_agent_id === $user->id
            : $ticket->user_id === $user->id;

        abort_unless($allowed, 403, 'You are not allowed to access this ticket.');
    }
}
