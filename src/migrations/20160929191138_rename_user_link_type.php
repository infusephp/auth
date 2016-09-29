<?php

use Phinx\Migration\AbstractMigration;

class RenameUserLinkType extends AbstractMigration
{
    public function change()
    {
        $this->table('UserLinks')
             ->renameColumn('link_type', 'type')
             ->changeColumn('type', 'string')
             ->update();
    }
}
