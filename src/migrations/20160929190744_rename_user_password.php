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

class RenameUserPassword extends AbstractMigration
{
    public function change()
    {
        $this->table('Users')
             ->renameColumn('user_password', 'password')
             ->update();
    }
}
