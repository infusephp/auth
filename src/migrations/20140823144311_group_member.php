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

class GroupMember extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('GroupMembers')) {
            $table = $this->table('GroupMembers', ['id' => false, 'primary_key' => ['group', 'uid']]);
            $table->addColumn('group', 'string')
                  ->addColumn('uid', 'integer')
                  ->addTimestamps()
                  ->create();
        }
    }
}
