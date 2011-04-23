<?php defined('SYSPATH') OR die('No direct access allowed.');

class Welcome_Controller extends Template_Controller {

	function index() 
	{
		$this->template->title = "Home Page";
		$this->template->content = new View('home/index');
	}
}
?>