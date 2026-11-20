<?php

$communities = env('SOCIAL_COMMUNITIES');
$memberships = $communities === null ? null : json_decode((string) $communities, true);

return [
    'communities' => $communities === null ? null : (is_array($memberships) ? $memberships : []),
];
