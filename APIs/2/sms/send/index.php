<?php
/**
 * API 82
 *
 * A mock API end-point to send sms. fails 77% of times.
 */

echo
    rand(0, 99) < 77
        ? json_encode(["status" => false])
        : json_encode(["status" => true]);