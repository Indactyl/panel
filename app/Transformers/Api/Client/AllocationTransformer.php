<?php

namespace DASHDACTYL\Transformers\Api\Client;

use DASHDACTYL\Models\Allocation;
use DASHDACTYL\Transformers\Api\Transformer;

class AllocationTransformer extends Transformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return 'allocation';
    }

    public function transform(Allocation $model): array
    {
        return [
            'id' => $model->id,
            'ip' => $model->ip,
            'ip_alias' => $model->ip_alias,
            'port' => $model->port,
            'notes' => $model->notes,
            'is_default' => $model->server->allocation_id === $model->id,
        ];
    }
}