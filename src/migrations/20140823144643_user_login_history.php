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

class UserLoginHistory extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('UserLoginHistories')) {
            $table = $this->table('UserLoginHistories');
            $table->addColumn('uid', 'integer')
                  ->addColumn('type', 'integer', ['length' => 1])
                  ->addColumn('ip', 'string', ['length' => 45])
                  ->addColumn('user_agent', 'string')
                  ->addTimestamps()
                  ->create();
        }
    }
}
