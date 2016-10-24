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

class RenameUserLinkType extends AbstractMigration
{
    public function change()
    {
        $this->table('UserLinks')
             ->renameColumn('link_type', 'type')
             ->changeColumn('type', 'string')
             ->update();
    }
}
