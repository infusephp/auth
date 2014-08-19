<?php

require_once 'vendor/autoload.php';

define( 'INFUSE_BASE_DIR', __DIR__ );
set_include_path( get_include_path() . PATH_SEPARATOR . INFUSE_BASE_DIR );

// shim for users model
require_once 'src/app/auth/DefaultUser.php';

// This is a hack for the tests to generate a table for the users
\app\auth\Controller::$properties[ 'models' ][] = 'User';