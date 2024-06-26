<?php

namespace DASHDACTYL\Http\Controllers\Api\Client\Servers;

use DASHDACTYL\Models\Server;
use Illuminate\Http\JsonResponse;
use DASHDACTYL\Facades\Activity;
use DASHDACTYL\Models\Allocation;
use DASHDACTYL\Exceptions\DisplayException;
use DASHDACTYL\Repositories\Eloquent\ServerRepository;
use DASHDACTYL\Transformers\Api\Client\AllocationTransformer;
use DASHDACTYL\Http\Controllers\Api\Client\ClientApiController;
use DASHDACTYL\Services\Allocations\FindAssignableAllocationService;
use DASHDACTYL\Http\Requests\Api\Client\Servers\Network\GetNetworkRequest;
use DASHDACTYL\Http\Requests\Api\Client\Servers\Network\NewAllocationRequest;
use DASHDACTYL\Http\Requests\Api\Client\Servers\Network\DeleteAllocationRequest;
use DASHDACTYL\Http\Requests\Api\Client\Servers\Network\UpdateAllocationRequest;
use DASHDACTYL\Http\Requests\Api\Client\Servers\Network\SetPrimaryAllocationRequest;

class NetworkAllocationController extends ClientApiController
{
    /**
     * NetworkAllocationController constructor.
     */
    public function __construct(
        private FindAssignableAllocationService $assignableAllocationService,
        private ServerRepository $serverRepository
    ) {
        parent::__construct();
    }

    /**
     * Lists all the allocations available to a server and whether
     * they are currently assigned as the primary for this server.
     */
    public function index(GetNetworkRequest $request, Server $server): array
    {
        return $this->fractal->collection($server->allocations)
            ->transformWith(AllocationTransformer::class)
            ->toArray();
    }

    /**
     * Set the primary allocation for a server.
     *
     * @throws \DASHDACTYL\Exceptions\Model\DataValidationException
     * @throws \DASHDACTYL\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateAllocationRequest $request, Server $server, Allocation $allocation): array
    {
        $original = $allocation->notes;

        $allocation->forceFill(['notes' => $request->input('notes')])->save();

        if ($original !== $allocation->notes) {
            Activity::event('server:allocation.notes')
                ->subject($allocation)
                ->property(['allocation' => $allocation->toString(), 'old' => $original, 'new' => $allocation->notes])
                ->log();
        }

        return $this->fractal->item($allocation)
            ->transformWith(AllocationTransformer::class)
            ->toArray();
    }

    /**
     * Set the primary allocation for a server.
     *
     * @throws \DASHDACTYL\Exceptions\Model\DataValidationException
     * @throws \DASHDACTYL\Exceptions\Repository\RecordNotFoundException
     */
    public function setPrimary(SetPrimaryAllocationRequest $request, Server $server, Allocation $allocation): array
    {
        $this->serverRepository->update($server->id, ['allocation_id' => $allocation->id]);

        Activity::event('server:allocation.primary')
            ->subject($allocation)
            ->property('allocation', $allocation->toString())
            ->log();

        return $this->fractal->item($allocation)
            ->transformWith(AllocationTransformer::class)
            ->toArray();
    }

    /**
     * Set the notes for the allocation for a server.
     *s.
     *
     * @throws \DASHDACTYL\Exceptions\DisplayException
     */
    public function store(NewAllocationRequest $request, Server $server): array
    {
        if ($server->allocations()->count() >= $server->allocation_limit) {
            throw new DisplayException('Cannot assign additional allocations to this server: limit has been reached.');
        }

        $allocation = $this->assignableAllocationService->handle($server);

        Activity::event('server:allocation.create')
            ->subject($allocation)
            ->property('allocation', $allocation->toString())
            ->log();

        return $this->fractal->item($allocation)
            ->transformWith(AllocationTransformer::class)
            ->toArray();
    }

    /**
     * Delete an allocation from a server.
     *
     * @throws \DASHDACTYL\Exceptions\DisplayException
     */
    public function delete(DeleteAllocationRequest $request, Server $server, Allocation $allocation): JsonResponse
    {
        // Don't allow the deletion of allocations if the server does not have an
        // allocation limit set.
        if (empty($server->allocation_limit)) {
            throw new DisplayException('You cannot delete allocations for this server: no allocation limit is set.');
        }

        if ($allocation->id === $server->allocation_id) {
            throw new DisplayException('You cannot delete the primary allocation for this server.');
        }

        Allocation::query()->where('id', $allocation->id)->update([
            'notes' => null,
            'server_id' => null,
        ]);

        Activity::event('server:allocation.delete')
            ->subject($allocation)
            ->property('allocation', $allocation->toString())
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}