<?php

require_once 'vendor/autoload.php';

define( 'INFUSE_BASE_DIR', __DIR__ );
set_include_path( get_include_path() . PATH_SEPARATOR . INFUSE_BASE_DIR );

/* Install DB Schema */

$config = @include 'config.php';
$config[ 'modules' ][ 'middleware' ] = [];
$config[ 'sessions' ][ 'enabled' ] = false;
$app = new App( $config );

$app->installSchema( true );