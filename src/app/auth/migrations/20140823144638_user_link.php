<?php

use Phinx\Migration\AbstractMigration;

class UserLink extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if( !$this->hasTable( 'UserLinks' ) )
        {
            $table = $this->table( 'UserLinks', [ 'id' => false, 'primary_key' => [ 'uid', 'link' ] ] );
            $table->addColumn( 'uid', 'integer' )
                  ->addColumn( 'link', 'string', [ 'length' => 32 ] )
                  ->addColumn( 'link_type', 'integer' )
                  ->addColumn( 'created_at', 'integer' )
                  ->addColumn( 'updated_at', 'integer', [ 'null' => true, 'default' => null ] )
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