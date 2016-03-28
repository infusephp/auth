<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth;

use App\Auth\Models\UserLink;
use App\Auth\Models\PersistentSession;

class Controller
{
    public function garbageCollection()
    {
        // clear out expired persistent sessions
        $persistentSessionSuccess = PersistentSession::garbageCollect();

        if ($persistentSessionSuccess) {
            echo "Garbage collection of persistent sessions was successful.\n";
        } else {
            echo "Garbage collection of persistent sessions was NOT successful.\n";
        }

        // clear out expired user links
        $userLinkSuccess = UserLink::garbageCollect();

        if ($userLinkSuccess) {
            echo "Garbage collection of user links was successful.\n";
        } else {
            echo "Garbage collection of user links was NOT successful.\n";
        }

        return $persistentSessionSuccess && $userLinkSuccess;
    }
}
