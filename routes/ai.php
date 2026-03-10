<?php

use App\Http\Middleware\AuthenticateMcpUrlToken;
use App\Mcp\Servers\Video2BookServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/video2book/{token}', Video2BookServer::class)
    ->middleware(AuthenticateMcpUrlToken::class);
