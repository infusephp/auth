<?php

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
