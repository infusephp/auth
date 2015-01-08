<?php

use Phinx\Migration\AbstractMigration;

class PersistentSession extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (!$this->hasTable('PersistentSessions')) {
            $table = $this->table('PersistentSessions', [ 'id' => false, 'primary_key' => 'token' ]);
            $table->addColumn('token', 'string', [ 'length' => 128 ])
                  ->addColumn('user_email', 'string')
                  ->addColumn('series', 'string', [ 'length' => 128 ])
                  ->addColumn('uid', 'integer')
                  ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
                  ->create();
        }
    }

    /**
     * Migrate Up.
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
