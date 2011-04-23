<?php

class User_Model extends ORM {
	
	protected $has_many = array('tweets');
	
	protected $primary_val = 'screen_name';
	
	public function validate(array $data, $save = FALSE)
	{
		// All the fields, that are not added automatically by ORM
		// MUST have at least one rule, else they won't be
		// entered into the database.

		$data = Validation::factory($data)
				->add_rules('screen_name', 'required', 'length[1,48]');
		return parent::validate($data, $save);
	}
	
	public function save()
	{
		// Add added_at field, if its a new row. Override, even if its set.
		if($this->loaded === FALSE)
		{
			$this->first_use = date('Y-m-d H:i:s');
		}
		else 
		{
			$this->last_use = date('Y-m-d H:i:s');
		}
		return parent::save();
	}
}
?>