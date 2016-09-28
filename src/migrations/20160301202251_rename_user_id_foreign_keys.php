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

class RenameUserIdForeignKeys extends AbstractMigration
{
    public function change()
    {
        $tables = [
            'GroupMembers',
            'PersistentSessions',
            'UserLinks',
            'UserLoginHistories',
        ];
        foreach ($tables as $table) {
            $this->table($table)
                 ->renameColumn('uid', 'user_id')
                 ->update();
        }
    }
}
