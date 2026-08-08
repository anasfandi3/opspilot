<?php

return [
    'invitation_lifetime_days' => (int) env('WORKSPACE_INVITATION_LIFETIME_DAYS', 7),
    'expose_invitation_tokens' => env('APP_ENV') === 'local' || env('APP_ENV') === 'testing',
];
