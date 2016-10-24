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

class AccountSecurityEventIpLength extends AbstractMigration
{
    public function change()
    {
        $this->table('AccountSecurityEvents')
             ->changeColumn('ip', 'string', ['length' => 45])
             ->update();
    }
}
