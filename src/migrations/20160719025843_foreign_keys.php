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

class ForeignKeys extends AbstractMigration
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
                 ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                 ->update();
        }
    }
}
