<?php

namespace DASHDACTYL\Tests\Integration\Api\Client\Server\Backup;

use Mockery\MockInterface;
use Illuminate\Http\Response;
use DASHDACTYL\Models\Backup;
use DASHDACTYL\Models\Permission;
use Illuminate\Support\Facades\Event;
use DASHDACTYL\Events\ActivityLogged;
use DASHDACTYL\Repositories\Wings\DaemonBackupRepository;
use DASHDACTYL\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class DeleteBackupTest extends ClientApiIntegrationTestCase
{
    private MockInterface $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->mock(DaemonBackupRepository::class);
    }

    public function testUserWithoutPermissionCannotDeleteBackup()
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_BACKUP_CREATE]);

        $backup = Backup::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)->deleteJson($this->link($backup))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Tests that a backup can be deleted for a server and that it is properly updated
     * in the database. Once deleted there should also be a corresponding record in the
     * activity logs table for this API call.
     */
    public function testBackupCanBeDeleted()
    {
        Event::fake([ActivityLogged::class]);

        [$user, $server] = $this->generateTestAccount([Permission::ACTION_BACKUP_DELETE]);

        /** @var \DASHDACTYL\Models\Backup $backup */
        $backup = Backup::factory()->create(['server_id' => $server->id]);

        $this->repository->expects('setServer->delete')->with(
            \Mockery::on(function ($value) use ($backup) {
                return $value instanceof Backup && $value->uuid === $backup->uuid;
            })
        )->andReturn(new Response());

        $this->actingAs($user)->deleteJson($this->link($backup))->assertStatus(Response::HTTP_NO_CONTENT);

        $backup->refresh();
        $this->assertSoftDeleted($backup);

        $this->assertActivityFor('server:backup.delete', $user, [$backup, $backup->server]);

        $this->actingAs($user)->deleteJson($this->link($backup))->assertStatus(Response::HTTP_NOT_FOUND);
    }
}