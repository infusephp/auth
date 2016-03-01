<?php

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
