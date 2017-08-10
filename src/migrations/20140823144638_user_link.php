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

class UserLink extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('UserLinks')) {
            $table = $this->table('UserLinks', ['id' => false, 'primary_key' => ['uid', 'link']]);
            $table->addColumn('uid', 'integer')
                  ->addColumn('link', 'string', ['length' => 32])
                  ->addColumn('link_type', 'integer', ['length' => 2])
                  ->addTimestamps()
                  ->create();
        }
    }
}
