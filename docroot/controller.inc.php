<?php

///  DEFINE THE PATHS relative to this file

///  MODIFY this line to set the path to the Controller.class.php
$__dirname_file = dirname(__FILE__);
require_once($__dirname_file. '/../Stark/Controller.class.php');
$ctl = new Stark__Controller
	///  MODIFY these to set paths to each resource (hardcoding absolute will be slightly faster)
	( $_SERVER['REQUEST_URI'],
	  array( 'lib_path'    	   => $__dirname_file .'/../lib', // directory with Stark libraries
			 'config_path' 	   => $__dirname_file .'/../application/stark-config.inc.php',
			 'controller_path' => $__dirname_file .'/../application/controller',
			 'model_path'      => $__dirname_file .'/../application/model',
			 )
	 );
unset($__dirname_file);

///  Common: add the model path to include_path
set_include_path(get_include_path() . PATH_SEPARATOR . $ctl->model_path);

///  Run the Main Handler
$ctl->handler();

///  Define a shortcut to the view...
$view = $ctl->view;
