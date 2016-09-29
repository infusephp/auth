<?php

use Phinx\Migration\AbstractMigration;

class RenameUserPassword extends AbstractMigration
{
    public function change()
    {
        $this->table('Users')
             ->renameColumn('user_password', 'password')
             ->update();
    }
}
