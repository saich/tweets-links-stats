<?php

class Tweet_Model extends ORM {

	protected $belongs_to = array('user');
	
	protected $has_and_belongs_to_many = array('domains');
}
?>