<?php

namespace Tests;

use App\Http\Controllers\DialogController;
use App\Services\ChatServiceClient;
use Illuminate\Http\Request;
use Mockery;

class DialogControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_read_marks_dialog_messages_as_read(): void
    {
        $client = Mockery::mock(ChatServiceClient::class);
        $client->shouldReceive('markDialogAsRead')
            ->once()
            ->with('req-1', 'user-1', 'user-2')
            ->andReturn([
                'marked_as_read' => 3,
                'dialog_unread' => 0,
                'total_unread' => 2,
            ]);

        $controller = new DialogController($client);
        $response = $controller->read($this->makeAuthenticatedRequest(), 'user-2');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'marked_as_read' => 3,
            'dialog_unread' => 0,
            'total_unread' => 2,
        ], json_decode($response->getContent(), true));
    }

    public function test_unread_counters_returns_total_and_dialog_breakdown(): void
    {
        $client = Mockery::mock(ChatServiceClient::class);
        $client->shouldReceive('getUnreadCounters')
            ->once()
            ->with('req-1', 'user-1', null)
            ->andReturn([
                'total_unread' => 5,
                'dialogs' => [
                    [
                        'user_id' => 'user-2',
                        'unread_count' => 3,
                    ],
                    [
                        'user_id' => 'user-3',
                        'unread_count' => 2,
                    ],
                ],
            ]);

        $controller = new DialogController($client);
        $response = $controller->unreadCounters($this->makeAuthenticatedRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'total_unread' => 5,
            'dialogs' => [
                [
                    'user_id' => 'user-2',
                    'unread_count' => 3,
                ],
                [
                    'user_id' => 'user-3',
                    'unread_count' => 2,
                ],
            ],
        ], json_decode($response->getContent(), true));
    }

    public function test_read_rejects_self_dialog(): void
    {
        $client = Mockery::mock(ChatServiceClient::class);
        $client->shouldNotReceive('markDialogAsRead');

        $controller = new DialogController($client);
        $response = $controller->read($this->makeAuthenticatedRequest(), 'user-1');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'error' => 'Cannot mark self dialog as read',
        ], json_decode($response->getContent(), true));
    }

    private function makeAuthenticatedRequest(): Request
    {
        $request = Request::create('/dialog/unread', 'GET');
        $request->attributes->set('request_id', 'req-1');
        $request->attributes->set('auth_user', (object) [
            'user_id' => 'user-1',
        ]);

        return $request;
    }
}
