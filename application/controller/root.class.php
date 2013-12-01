<?php
class root extends Stark__Controller__Base {

	public function directory_handler($ctl, $path) {
		///  Authentication / Session stuff here...

		return true;
	}

	public function index_page($ctl) {
		return( array( ) );
	}
}
