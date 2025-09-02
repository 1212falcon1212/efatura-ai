<?php

return [
    'failure_threshold' => env('CB_FAILURE_THRESHOLD', 5),
    'open_seconds' => env('CB_OPEN_SECONDS', 300),
    'half_open_max_trials' => env('CB_HALF_OPEN_MAX_TRIALS', 3),
];


