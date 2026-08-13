<?php

declare(strict_types=1);

return [
    // Bounded Docker control agent (docker/agent/agent.py). Empty = disabled;
    // the admin Containers page then shows operator commands only. Set both to
    // enable in-app container control on a trusted home deployment.
    'agent_url' => env('DOCKER_AGENT_URL', 'http://agent:9000'),
    'agent_token' => env('DOCKER_AGENT_TOKEN', ''),
];
