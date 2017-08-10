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

class user extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('Users')) {
            $table = $this->table('Users', ['id' => 'uid']);
            $table->addColumn('user_email', 'string')
                  ->addColumn('user_password', 'string', ['length' => 128])
                  ->addColumn('first_name', 'string')
                  ->addColumn('last_name', 'string')
                  ->addColumn('ip', 'string', ['length' => 45])
                  ->addColumn('enabled', 'boolean', ['default' => true])
                  ->addTimestamps()
                  ->addIndex('user_email', ['unique' => true])
                  ->create();
        }
    }
}
