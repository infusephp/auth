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
                  ->addColumn('created_at', 'integer')
                  ->addColumn('updated_at', 'integer', [ 'null' => true, 'default' => null ])
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
