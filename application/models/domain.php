<?php

class Domain_Model extends ORM {
	
	protected $has_and_belongs_to_many = array('tweets');
	
	protected $primary_val = 'name';
	
	public function unique_key($id = NULL)
	{
		if ( !empty($id) AND is_string($id) AND !ctype_digit($id) )
		{
			return 'name';
		}
		return parent::unique_key($id);
	}
}
?>