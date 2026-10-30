<?php

use App\Mcp\Servers\DataHubServer;
use Laravel\Mcp\Facades\Mcp;

/*
| MCP for Claude. Clients authenticate with a personal access token that has the "mcp" ability
| (create one with: php artisan app:issue-mcp-token you@example.com) and act with that user's permissions.
*/
Mcp::web('/mcp', DataHubServer::class)
    ->middleware(['auth:sanctum', 'abilities:mcp', 'throttle:mcp']);
