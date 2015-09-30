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

class UserLoginHistoryType extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $this->table('UserLoginHistories')->changeColumn('type', 'string', ['length' => 50]);

        $rows = $this->fetchAll('SELECT * FROM UserLoginHistories');

        foreach ($rows as $row) {
            $type = $row['type'];
            if ($type == 0) {
                $type = 'web';
            } elseif ($type == 1) {
                $type == 'persistent';
            } elseif ($type == 2) {
                $type = 'oauth';
            } elseif ($type == 3) {
                $type = 'facebook';
            } elseif ($type == 4) {
                $type = 'twitter';
            } elseif ($type == 5) {
                $type = 'instagram';
            }

            $this->execute('UPDATE UserLoginHistories SET type = "'.$type.'" WHERE id = '.$row['id']);
        }
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
    }
}
