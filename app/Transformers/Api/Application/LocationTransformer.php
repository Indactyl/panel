<?php

namespace DASHDACTYL\Transformers\Api\Application;

use DASHDACTYL\Models\Location;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\NullResource;
use DASHDACTYL\Services\Acl\Api\AdminAcl;
use DASHDACTYL\Transformers\Api\Transformer;

class LocationTransformer extends Transformer
{
    /**
     * List of resources that can be included.
     */
    protected array $availableIncludes = ['nodes', 'servers'];

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Location::RESOURCE_NAME;
    }

    /**
     * Return a generic transformed location array.
     */
    public function transform(Location $model): array
    {
        return [
            'id' => $model->id,
            'short' => $model->short,
            'long' => $model->long,
            'created_at' => self::formatTimestamp($model->created_at),
            'updated_at' => self::formatTimestamp($model->updated_at),
        ];
    }

    /**
     * Return the nodes associated with this location.
     */
    public function includeNodes(Location $location): Collection|NullResource
    {
        if (!$this->authorize(AdminAcl::RESOURCE_NODES)) {
            return $this->null();
        }

        return $this->collection($location->nodes, new NodeTransformer());
    }

    /**
     * Return the nodes associated with this location.
     */
    public function includeServers(Location $location): Collection|NullResource
    {
        if (!$this->authorize(AdminAcl::RESOURCE_SERVERS)) {
            return $this->null();
        }

        return $this->collection($location->servers, new ServerTransformer());
    }
}