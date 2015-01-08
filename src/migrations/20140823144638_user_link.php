<?php

use Phinx\Migration\AbstractMigration;

class UserLink extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (!$this->hasTable('UserLinks')) {
            $table = $this->table('UserLinks', [ 'id' => false, 'primary_key' => [ 'uid', 'link' ] ]);
            $table->addColumn('uid', 'integer')
                  ->addColumn('link', 'string', [ 'length' => 32 ])
                  ->addColumn('link_type', 'integer', [ 'length' => 2 ])
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
