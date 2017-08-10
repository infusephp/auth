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

class PersistentSession extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('PersistentSessions')) {
            $table = $this->table('PersistentSessions', ['id' => false, 'primary_key' => 'token']);
            $table->addColumn('token', 'string', ['length' => 128])
                  ->addColumn('user_email', 'string')
                  ->addColumn('series', 'string', ['length' => 128])
                  ->addColumn('uid', 'integer')
                  ->addTimestamps()
                  ->create();
        }
    }
}
