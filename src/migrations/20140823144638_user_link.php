<?php

/**
 * @package infuse\auth
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @copyright 2015 Jared King
 * @license MIT
 */

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
