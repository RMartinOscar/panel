<?php

namespace App\Http\Controllers\Api\Remote\Servers;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerContainersController extends Controller
{
    /**
     * Updates the server container's status on the Panel
     */
    public function status(Server $server, Request $request): JsonResponse
    {
        $status = fluent($request->json()->all())->get('data.new_state');

        cache()->set("servers.$server->uuid.container.status", $status, now()->addHour());

        return new JsonResponse([]);
    }
}
