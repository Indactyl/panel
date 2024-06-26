<?php

namespace DASHDACTYL\Http\Controllers\Api\Application\Nodes;

use DASHDACTYL\Models\Node;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use DASHDACTYL\Services\Nodes\NodeUpdateService;
use DASHDACTYL\Services\Nodes\NodeCreationService;
use DASHDACTYL\Services\Nodes\NodeDeletionService;
use DASHDACTYL\Transformers\Api\Application\NodeTransformer;
use DASHDACTYL\Exceptions\Http\QueryValueOutOfRangeHttpException;
use DASHDACTYL\Http\Requests\Api\Application\Nodes\GetNodeRequest;
use DASHDACTYL\Http\Requests\Api\Application\Nodes\GetNodesRequest;
use DASHDACTYL\Http\Requests\Api\Application\Nodes\StoreNodeRequest;
use DASHDACTYL\Http\Requests\Api\Application\Nodes\DeleteNodeRequest;
use DASHDACTYL\Http\Requests\Api\Application\Nodes\UpdateNodeRequest;
use DASHDACTYL\Http\Controllers\Api\Application\ApplicationApiController;

class NodeController extends ApplicationApiController
{
    /**
     * NodeController constructor.
     */
    public function __construct(
        private NodeCreationService $creationService,
        private NodeDeletionService $deletionService,
        private NodeUpdateService $updateService
    ) {
        parent::__construct();
    }

    /**
     * Return all the nodes currently available on the Panel.
     */
    public function index(GetNodesRequest $request): array
    {
        $perPage = (int) $request->query('per_page', '10');
        if ($perPage < 1 || $perPage > 100) {
            throw new QueryValueOutOfRangeHttpException('per_page', 1, 100);
        }

        $nodes = QueryBuilder::for(Node::query())
            ->allowedFilters(['id', 'uuid', 'name', 'fqdn', 'daemon_token_id'])
            ->allowedSorts(['id', 'uuid', 'name', 'location_id', 'fqdn', 'memory', 'disk'])
            ->paginate($perPage);

        return $this->fractal->collection($nodes)
            ->transformWith(NodeTransformer::class)
            ->toArray();
    }

    /**
     * Return data for a single instance of a node.
     */
    public function view(GetNodeRequest $request, Node $node): array
    {
        return $this->fractal->item($node)
            ->transformWith(NodeTransformer::class)
            ->toArray();
    }

    /**
     * Create a new node on the Panel. Returns the created node and an HTTP/201
     * status response on success.
     *
     * @throws \DASHDACTYL\Exceptions\Model\DataValidationException
     */
    public function store(StoreNodeRequest $request): JsonResponse
    {
        $node = $this->creationService->handle($request->validated());

        return $this->fractal->item($node)
            ->transformWith(NodeTransformer::class)
            ->respond(201);
    }

    /**
     * Update an existing node on the Panel.
     *
     * @throws \Throwable
     */
    public function update(UpdateNodeRequest $request, Node $node): array
    {
        $node = $this->updateService->handle(
            $node,
            $request->validated(),
        );

        return $this->fractal->item($node)
            ->transformWith(NodeTransformer::class)
            ->toArray();
    }

    /**
     * Deletes a given node from the Panel as long as there are no servers
     * currently attached to it.
     *
     * @throws \DASHDACTYL\Exceptions\Service\HasActiveServersException
     */
    public function delete(DeleteNodeRequest $request, Node $node): Response
    {
        $this->deletionService->handle($node);

        return $this->returnNoContent();
    }
}