<?php

use Phinx\Migration\AbstractMigration;

class GroupMember extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if( !$this->hasTable( 'GroupMember' ) )
        {
            $table = $this->table( 'GroupMember', [ 'id' => false, 'primary_key' => [ 'group', 'uid' ] ] );
            $table->addColumn( 'group', 'string' )
                  ->addColumn( 'uid', 'integer' )
                  ->addColumn( 'created_at', 'timestamp' )
                  ->addColumn( 'updated_at', 'timestamp', [ 'null' => true, 'default' => null ] );
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