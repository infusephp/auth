<?php

use Phinx\Migration\AbstractMigration;

class user extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (!$this->hasTable('Users')) {
            $table = $this->table('Users', [ 'id' => 'uid' ]);
            $table->addColumn('user_email', 'string')
                  ->addColumn('user_password', 'string', [ 'length' => 128 ])
                  ->addColumn('first_name', 'string')
                  ->addColumn('last_name', 'string')
                  ->addColumn('ip', 'string', [ 'length' => 45 ])
                  ->addColumn('enabled', 'boolean', [ 'default' => true ])
                  ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                  ->addColumn('updated_at', 'timestamp', ['null' => true, 'default' => null, 'update' => 'CURRENT_TIMESTAMP'])
                  ->addIndex('user_email', [ 'unique' => true ])
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
