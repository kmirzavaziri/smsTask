<?php

echo
    rand(0, 99) < 63
        ? json_encode(["status" => false])
        : json_encode(["status" => true]);