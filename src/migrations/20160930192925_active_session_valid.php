<?php

use Phinx\Migration\AbstractMigration;

class ActiveSessionValid extends AbstractMigration
{
    public function change()
    {
        $this->table('ActiveSessions')
             ->addColumn('valid', 'boolean', ['default' => true])
             ->update();
    }
}
