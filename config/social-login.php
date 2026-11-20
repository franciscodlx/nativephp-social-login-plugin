<?php

return [
    /*
    | URL schemes your redirects use (comma-separated in .env), e.g. "myapp".
    | iOS needs nothing: ASWebAuthenticationSession catches its scheme. On
    | Android a post_compile hook adds them to the redirect activity's
    | intent-filter; rebuild after changing them.
    */
    'redirect_schemes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SOCIAL_LOGIN_REDIRECT_SCHEMES', ''))
    ))),

    /* Seconds a sign-in may stay open in the browser. */
    'pending_ttl' => (int) env('SOCIAL_LOGIN_PENDING_TTL', 600),
];
