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

class ActiveSessionValid extends AbstractMigration
{
    public function change()
    {
        $this->table('ActiveSessions')
             ->addColumn('valid', 'boolean', ['default' => true])
             ->update();
    }
}
