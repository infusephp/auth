<?php

use Phinx\Migration\AbstractMigration;

class RenameUserUid extends AbstractMigration
{
    public function change()
    {
        $this->table('Users')
             ->renameColumn('uid', 'id')
             ->update();
    }
}
