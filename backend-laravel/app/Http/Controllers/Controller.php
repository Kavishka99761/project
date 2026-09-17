<?php

namespace App\Http\Controllers;


/**
 * Base controller for the EDU-SMART API.
 *
 * Laravel 11 keeps this intentionally minimal — routing, middleware and
 * validation are configured in bootstrap/app.php and via the Request object,
 * so no AuthorizesRequests/ValidatesRequests traits are needed here.
 */
abstract class Controller
{
    //
}
