<?php

namespace App\Tests\Feature;

use App\Jobs\ProcessWebhook;
use App\Models\Server;
use App\Models\WebhookConfiguration;
use App\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

class DispatchWebhooksTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_sends_a_single_webhook(): void
    {
        WebhookConfiguration::factory()->create([
            'events' => ['eloquent.created: '.Server::class],
        ]);

        $this->createServer();

        Queue::assertPushed(ProcessWebhook::class);
    }

    public function test_sends_multiple_webhooks()
    {
        WebhookConfiguration::factory(2)
            ->create(['events' => ['eloquent.created: '.Server::class]]);

        $this->createServer();

        Queue::assertPushed(ProcessWebhook::class, 2);
    }

    public function test_it_sends_no_webhooks()
    {
        WebhookConfiguration::factory()->create();

        $this->createServer();

        Queue::assertNothingPushed();
    }

    public function test_it_sends_some_webhooks()
    {
        WebhookConfiguration::factory(2)
            ->sequence(
                ['events' => ['eloquent.created: '.Server::class]],
                ['events' => ['eloquent.deleted: '.Server::class]]
            )->create();

        $this->createServer();

        Queue::assertPushed(ProcessWebhook::class, 1);
    }

    public function createServer(): Server
    {
        return Server::factory()->withNode()->create();
    }
}
