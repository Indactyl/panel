<?php

namespace DASHDACTYL\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Response;
use DASHDACTYL\Models\Server;
use DASHDACTYL\Facades\Activity;
use DASHDACTYL\Repositories\Wings\DaemonPowerRepository;
use DASHDACTYL\Http\Controllers\Api\Client\ClientApiController;
use DASHDACTYL\Http\Requests\Api\Client\Servers\SendPowerRequest;

class PowerController extends ClientApiController
{
    /**
     * PowerController constructor.
     */
    public function __construct(private DaemonPowerRepository $repository)
    {
        parent::__construct();
    }

    /**
     * Send a power action to a server.
     */
    public function index(SendPowerRequest $request, Server $server): Response
    {
        $this->repository->setServer($server)->send(
            $request->input('signal')
        );

        Activity::event(strtolower("server:power.{$request->input('signal')}"))->log();

        return $this->returnNoContent();
    }
}