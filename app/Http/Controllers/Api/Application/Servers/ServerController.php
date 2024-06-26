<?php

namespace DASHDACTYL\Http\Controllers\Api\Application\Servers;

use Illuminate\Http\Response;
use DASHDACTYL\Models\Server;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use DASHDACTYL\Services\Servers\ServerCreationService;
use DASHDACTYL\Services\Servers\ServerDeletionService;
use DASHDACTYL\Services\Servers\BuildModificationService;
use DASHDACTYL\Services\Servers\DetailsModificationService;
use DASHDACTYL\Transformers\Api\Application\ServerTransformer;
use DASHDACTYL\Exceptions\Http\QueryValueOutOfRangeHttpException;
use DASHDACTYL\Http\Requests\Api\Application\Servers\GetServerRequest;
use DASHDACTYL\Http\Requests\Api\Application\Servers\GetServersRequest;
use DASHDACTYL\Http\Requests\Api\Application\Servers\ServerWriteRequest;
use DASHDACTYL\Http\Requests\Api\Application\Servers\StoreServerRequest;
use DASHDACTYL\Http\Controllers\Api\Application\ApplicationApiController;
use DASHDACTYL\Http\Requests\Api\Application\Servers\UpdateServerRequest;

class ServerController extends ApplicationApiController
{
    /**
     * ServerController constructor.
     */
    public function __construct(
        private BuildModificationService $buildModificationService,
        private DetailsModificationService $detailsModificationService,
        private ServerCreationService $creationService,
        private ServerDeletionService $deletionService
    ) {
        parent::__construct();
    }

    /**
     * Return all the servers that currently exist on the Panel.
     */
    public function index(GetServersRequest $request): array
    {
        $perPage = (int) $request->query('per_page', '10');
        if ($perPage < 1 || $perPage > 100) {
            throw new QueryValueOutOfRangeHttpException('per_page', 1, 100);
        }

        $servers = QueryBuilder::for(Server::query())
            ->allowedFilters(['id', 'uuid', 'uuidShort', 'name', 'owner_id', 'node_id', 'external_id'])
            ->allowedSorts(['id', 'uuid', 'uuidShort', 'name', 'owner_id', 'node_id', 'status'])
            ->paginate($perPage);

        return $this->fractal->collection($servers)
            ->transformWith(ServerTransformer::class)
            ->toArray();
    }

    /**
     * Create a new server on the system.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     * @throws \DASHDACTYL\Exceptions\DisplayException
     * @throws \DASHDACTYL\Exceptions\Repository\RecordNotFoundException
     * @throws \DASHDACTYL\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \DASHDACTYL\Exceptions\Service\Deployment\NoViableNodeException
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        $server = $this->creationService->handle($request->validated());

        return $this->fractal->item($server)
            ->transformWith(ServerTransformer::class)
            ->respond(Response::HTTP_CREATED);
    }

    /**
     * Show a single server transformed for the application API.
     */
    public function view(GetServerRequest $request, Server $server): array
    {
        return $this->fractal->item($server)
            ->transformWith(ServerTransformer::class)
            ->toArray();
    }

    /**
     * Deletes a server.
     *
     * @throws \DASHDACTYL\Exceptions\DisplayException
     * @throws \Throwable
     */
    public function delete(ServerWriteRequest $request, Server $server, string $force = ''): Response
    {
        $this->deletionService->withForce($force === 'force')->handle($server);

        return $this->returnNoContent();
    }

    /**
     * Update a server.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     * @throws \DASHDACTYL\Exceptions\DisplayException
     * @throws \DASHDACTYL\Exceptions\Repository\RecordNotFoundException
     * @throws \DASHDACTYL\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \DASHDACTYL\Exceptions\Service\Deployment\NoViableNodeException
     */
    public function update(UpdateServerRequest $request, Server $server): array
    {
        $server = $this->buildModificationService->handle($server, $request->validated());
        $server = $this->detailsModificationService->returnUpdatedModel()->handle($server, $request->validated());

        return $this->fractal->item($server)
            ->transformWith(ServerTransformer::class)
            ->toArray();
    }
}