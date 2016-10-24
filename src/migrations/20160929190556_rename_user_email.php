<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Phinx\Migration\AbstractMigration;

class RenameUserEmail extends AbstractMigration
{
    public function change()
    {
        $this->table('Users')
             ->renameColumn('user_email', 'email')
             ->update();

        $this->table('PersistentSessions')
             ->renameColumn('user_email', 'email')
             ->update();
    }
}
