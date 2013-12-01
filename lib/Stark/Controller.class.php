<?php

require_once(dirname(__FILE__). '/stark-config.inc.php');
//  Load debugging
if ( ! function_exists('START_TIMER') )
    require_once(dirname(__FILE__). '/debug.inc.php');
//  Load per-site config if it's there
if ( file_exists( $_SERVER['DOCUMENT_ROOT']. '/lib/bootstrap.inc.php' ) )
    require_once($_SERVER['DOCUMENT_ROOT']. '/lib/bootstrap.inc.php');

//  Load Main Libraries
if ( ! empty( $STARK_PROFILING ) ) START_TIMER('STARK-controller-lib_load');
require_once(dirname(__FILE__). '/stark.class.php');
require_once(dirname(__FILE__). '/controller-base.class.php');
if ( isset( $STARK_PRELOAD_LIBS ) ) foreach( $STARK_PRELOAD_LIBS as $lib ) require_once($lib);
if ( ! empty( $STARK_PROFILING ) ) END_TIMER('STARK-controller-lib_load');

//  Main Controller Sequence
if ( ! empty( $STARK_PROFILING ) ) START_TIMER('STARK-controller-handlers');
$stark = new Stark($_SERVER['SCRIPT_NAME']);

if ( ! empty( $STARK_PROFILING ) ) END_TIMER('STARK-controller-handlers');
