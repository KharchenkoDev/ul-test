<?php

return [

    /*
     * Вероятность случайного сбоя для mock-провайдеров (0.0–1.0).
     * В тестах переопределяется через config(['notification.mock_fail_rate' => 0]).
     */
    'mock_fail_rate' => env('NOTIFICATION_MOCK_FAIL_RATE', 0.2),

];
