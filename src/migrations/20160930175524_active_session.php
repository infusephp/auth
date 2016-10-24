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

class ActiveSession extends AbstractMigration
{
    public function change()
    {
        $this->table('ActiveSessions', ['id' => false, 'primary_key' => ['id']])
             ->addColumn('id', 'string')
             ->addColumn('user_id', 'integer')
             ->addColumn('ip', 'string', ['length' => 45])
             ->addColumn('user_agent', 'string')
             ->addColumn('expires', 'integer')
             ->addColumn('created_at', 'timestamp', ['default' => 0])
             ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
             ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
             ->create();
    }
}
