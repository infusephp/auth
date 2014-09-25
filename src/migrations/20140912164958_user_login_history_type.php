<?php

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
            if ($type == 0)
                $type = 'web';
            else if ($type == 1)
                $type == 'persistent';
            else if ($type == 2)
                $type = 'oauth';
            else if ($type == 3)
                $type = 'facebook';
            else if ($type == 4)
                $type = 'twitter';
            else if ($type == 5)
                $type = 'instagram';

            $this->execute('UPDATE UserLoginHistories SET type = "' . $type . '" WHERE id = ' . $row['id']);
        }
    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }
}