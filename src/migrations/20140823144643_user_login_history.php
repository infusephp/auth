<?php

use Phinx\Migration\AbstractMigration;

class UserLoginHistory extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (!$this->hasTable('UserLoginHistories')) {
            $table = $this->table('UserLoginHistories');
            $table->addColumn('uid', 'integer')
                  ->addColumn('type', 'integer', [ 'length' => 1 ])
                  ->addColumn('ip', 'string', [ 'length' => 45 ])
                  ->addColumn('user_agent', 'string')
                  ->addColumn('created_at', 'timestamp', ['default' => 0])
                  ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
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
