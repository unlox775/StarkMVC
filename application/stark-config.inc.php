<?php

/////////////////////////
/// Stark MVC Config
$config->CONTROLLER_PRELOAD_LIBS
= array();

///  Controller configuration
$config->CONTROLLER_CLASS_PREFIX = '';


/////////////////////////
///  Skin Configuration

$ctl->includes_base = $_SERVER['DOCUMENT_ROOT'] .'/includes';

///  Make scoped_include() keep the $stage var
# $config->scope_global_vars[] = '';
