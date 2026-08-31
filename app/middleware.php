<?php

declare(strict_types=1);

use Slim\App;

return function (App $app) {
    // Nothing to add. The session middleware that used to live here started a
    // session whenever an Authorization header was present, in a service that
    // has no accounts and keeps no state.
};
