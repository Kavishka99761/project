<?php

use App\Support\Activity\ActivityContext;

if (! function_exists('activity')) {
    /**
     * The current request's activity record (see RecordActivity middleware).
     */
    function activity(): ActivityContext
    {
        return app(ActivityContext::class);
    }
}
