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

class ChangeUserLinkTypeType extends AbstractMigration
{
    public function change()
    {
        $this->execute('UPDATE UserLinks SET `type`="reset_password" WHERE `type`=0');
        $this->execute('UPDATE UserLinks SET `type`="verify_email" WHERE `type`=1');
        $this->execute('UPDATE UserLinks SET `type`="temporary" WHERE `type`=2');

        $this->table('UserLinks')
             ->changeColumn('type', 'enum', ['values' => ['reset_password', 'verify_email', 'temporary']])
             ->update();
    }
}
