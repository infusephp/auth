<?php

use Phinx\Migration\AbstractMigration;

class GroupMember extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (!$this->hasTable('GroupMembers')) {
            $table = $this->table('GroupMembers', [ 'id' => false, 'primary_key' => [ 'group', 'uid' ] ]);
            $table->addColumn('group', 'string')
                  ->addColumn('uid', 'integer')
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
