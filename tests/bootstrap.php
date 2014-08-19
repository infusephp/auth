<?php

require_once 'vendor/autoload.php';

define( 'INFUSE_BASE_DIR', __DIR__ );
set_include_path( get_include_path() . PATH_SEPARATOR . INFUSE_BASE_DIR );

require_once 'src/app/auth/DefaultUser.php';

// hack to ensure test user model schema is generated
\app\auth\Controller::$properties[ 'models' ][] = 'User';