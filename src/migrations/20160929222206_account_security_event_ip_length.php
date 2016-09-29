<?php

use Phinx\Migration\AbstractMigration;

class AccountSecurityEventIpLength extends AbstractMigration
{
    public function change()
    {
        $this->table('AccountSecurityEvents')
             ->changeColumn('ip', 'string', ['length' => 45])
             ->update();
    }
}
