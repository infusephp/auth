<?php

use Phinx\Migration\AbstractMigration;

class PersistentSessionTwoFactorVerified extends AbstractMigration
{
    public function change()
    {
        $this->table('PersistentSessions')
             ->addColumn('two_factor_verified', 'boolean')
             ->update();
    }
}
