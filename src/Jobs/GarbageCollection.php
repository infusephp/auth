<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth\Jobs;

use App\Auth\Models\UserLink;
use App\Auth\Models\PersistentSession;

class GarbageCollection
{
    public function __invoke($run)
    {
        // clear out expired persistent sessions
        $persistentSessionSuccess = PersistentSession::garbageCollect();

        if ($persistentSessionSuccess) {
            $run->writeOutput('Garbage collection of persistent sessions was successful.');
        } else {
            $run->writeOutput('Garbage collection of persistent sessions was NOT successful.');
        }

        // clear out expired user links
        $userLinkSuccess = UserLink::garbageCollect();

        if ($userLinkSuccess) {
            $run->writeOutput('Garbage collection of user links was successful.');
        } else {
            $run->writeOutput('Garbage collection of user links was NOT successful.');
        }

        return $persistentSessionSuccess && $userLinkSuccess;
    }
}
