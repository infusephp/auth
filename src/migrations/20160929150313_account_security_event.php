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

class AccountSecurityEvent extends AbstractMigration
{
    public function change()
    {
        $this->table('UserLoginHistories')
             ->rename('AccountSecurityEvents')
             ->renameColumn('type', 'auth_strategy')
             ->addColumn('type', 'string', ['after' => 'user_id'])
             ->addColumn('description', 'string', ['after' => 'user_agent'])
             ->update();

        $this->table('AccountSecurityEvents')
             ->changeColumn('auth_strategy', 'string', ['null' => true, 'default' => null, 'after' => 'description'])
             ->update();

        $this->execute('UPDATE AccountSecurityEvents SET type="user.login"');
    }
}
