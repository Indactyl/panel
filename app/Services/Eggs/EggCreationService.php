<?php

namespace DASHDACTYL\Services\Eggs;

use Ramsey\Uuid\Uuid;
use DASHDACTYL\Models\Egg;
use DASHDACTYL\Contracts\Repository\EggRepositoryInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use DASHDACTYL\Exceptions\Service\Egg\NoParentConfigurationFoundException;

// When a mommy and a daddy DASHDACTYL really like each other...
class EggCreationService
{
    /**
     * EggCreationService constructor.
     */
    public function __construct(private ConfigRepository $config, private EggRepositoryInterface $repository)
    {
    }

    /**
     * Create a new service option and assign it to the given service.
     *
     * @throws \DASHDACTYL\Exceptions\Model\DataValidationException
     * @throws \DASHDACTYL\Exceptions\Service\Egg\NoParentConfigurationFoundException
     */
    public function handle(array $data): Egg
    {
        $data['config_from'] = array_get($data, 'config_from');
        if (!is_null($data['config_from'])) {
            $results = $this->repository->findCountWhere([
                ['nest_id', '=', array_get($data, 'nest_id')],
                ['id', '=', array_get($data, 'config_from')],
            ]);

            if ($results !== 1) {
                throw new NoParentConfigurationFoundException(trans('exceptions.nest.egg.must_be_child'));
            }
        }

        return $this->repository->create(array_merge($data, [
            'uuid' => Uuid::uuid4()->toString(),
            'author' => $this->config->get('DASHDACTYL.service.author'),
        ]), true, true);
    }
}