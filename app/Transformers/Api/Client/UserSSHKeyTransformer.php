<?php

namespace DASHDACTYL\Transformers\Api\Client;

use DASHDACTYL\Models\UserSSHKey;
use DASHDACTYL\Transformers\Api\Transformer;

class UserSSHKeyTransformer extends Transformer
{
    public function getResourceName(): string
    {
        return UserSSHKey::RESOURCE_NAME;
    }

    /**
     * Return's a user's SSH key in an API response format.
     */
    public function transform(UserSSHKey $model): array
    {
        return [
            'name' => $model->name,
            'fingerprint' => $model->fingerprint,
            'public_key' => $model->public_key,
            'created_at' => self::formatTimestamp($model->created_at),
        ];
    }
}