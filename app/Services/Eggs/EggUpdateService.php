<?php

namespace DASHDACTYL\Services\Eggs;

use DASHDACTYL\Models\Egg;
use DASHDACTYL\Contracts\Repository\EggRepositoryInterface;
use DASHDACTYL\Exceptions\Service\Egg\NoParentConfigurationFoundException;

class EggUpdateService
{
    /**
     * EggUpdateService constructor.
     */
    public function __construct(protected EggRepositoryInterface $repository)
    {
    }

    /**
     * Update a service option.
     *
     * @throws \DASHDACTYL\Exceptions\Model\DataValidationException
     * @throws \DASHDACTYL\Exceptions\Repository\RecordNotFoundException
     * @throws \DASHDACTYL\Exceptions\Service\Egg\NoParentConfigurationFoundException
     */
    public function handle(Egg $egg, array $data): void
    {
        if (!is_null(array_get($data, 'config_from'))) {
            $results = $this->repository->findCountWhere([
                ['nest_id', '=', $egg->nest_id],
                ['id', '=', array_get($data, 'config_from')],
            ]);

            if ($results !== 1) {
                throw new NoParentConfigurationFoundException(trans('exceptions.nest.egg.must_be_child'));
            }
        }

        // TODO(dane): Once the admin UI is done being reworked and this is exposed
        //  in said UI, remove this so that you can actually update the denylist.
        unset($data['file_denylist']);

        $this->repository->withoutFreshModel()->update($egg->id, $data);
    }
}