<?php

echo
    rand(0, 99) < 77
        ? json_encode(["status" => false])
        : json_encode(["status" => true]);