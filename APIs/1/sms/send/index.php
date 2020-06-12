<?php
/**
 * API 81
 *
 * A mock API end-point to send sms. fails 63% of times.
 */

 echo
    rand(0, 99) < 63
        ? json_encode(["status" => false])
        : json_encode(["status" => true]);