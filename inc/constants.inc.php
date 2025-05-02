<?php
if (!defined("_CONSTANTS_INC_")) {

    define("_CONSTANTS_INC_", 1);

    define("APP_ROOT", __DIR__ . '/../');

    define("PG_DEFAULT_ROWS", 50);

    date_default_timezone_set('Europe/Rome');

    define("OPERATION_READ", 0);
    define("OPERATION_CREATE", 1);
    define("OPERATION_UPDATE", 2);
    define("OPERATION_DELETE", 3);
}