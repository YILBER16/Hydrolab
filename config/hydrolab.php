<?php

return [
    'device_token' => env('HYDROLAB_DEVICE_TOKEN'),

    /*
    | Minutes to wait between automatic Gemini alerts when parameters
    | stay out of range. Prevents repeated API calls on every ESP32 POST.
    */
    'ai_alert_cooldown_minutes' => (int) env('HYDROLAB_AI_ALERT_COOLDOWN', 45),

    /*
    | Sensor types that can trigger automatic AI recommendations.
    */
    'ai_alert_sensor_types' => ['temperature', 'ph'],
];
