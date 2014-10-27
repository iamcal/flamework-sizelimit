<?php
	#
	# This library registers a shutdown function which
	# checks the process size of the apache child. If the child
	# exeeds the configured limits, it is told to shutdown
	# cleanly after the request.
	#
	# This only works on Linux, under Apache, with prefork MPM.
	# 
	# Configuration:
	#
	# $GLOBALS['cfg']['sizelimit_max_mem']		= 0; # maximum total process size (in bytes)
	# $GLOBALS['cfg']['sizelimit_page_size']	= 0; # page size (in bytes) - use `getconf PAGESIZE` to check it's 4096
	#

	############################################################

	#
	# set up our shutdown handler, if we've been configured
	#

	if ($GLOBALS['cfg']['sizelimit_max_mem']){

		register_shutdown_function('sizelimit_check');

		$GLOBALS['_sizelimit_initial_state'] = sizelimit_status();
	}

	############################################################

	function sizelimit_check(){

		$new = sizelimit_status();
		$old = $GLOBALS['_sizelimit_initial_state'];

		if (!$new['virtual']) return;


		#
		# display status
		#

		$pid = getmypid();

		$res	= sizelimit_format($new['resident']);
		$share	= sizelimit_format($new['shared']);
		$virt	= sizelimit_format($new['virtual']);

		$d_resident = $new['resident'] - $old['resident'];

		if ($d_resident){
			$d_res = sizelimit_format($d_resident);
			$res .= " (delta $d_res)";
		}

		$unshared = $new['resident'] - $new['shared'];

		$unshare = sizelimit_format($unshared);


		log_notice('sizelimit', "PID-{$pid} Resident: {$res}, Unshared: {$unshare}, Shared: {$share}, Virtual: $virt");


		#
		# check if unshared memory is over limit
		#

		if ($unshared >= $GLOBALS['cfg']['sizelimit_max_mem']){

			$excess = $unshared - $GLOBALS['cfg']['sizelimit_max_mem'];

			log_notice('sizelimit', "Exceeded limit by ".sizelimit_format($excess));

			register_shutdown_function('sizelimit_terminate');
		}
	}

	############################################################

	function sizelimit_status(){

		#
		# We need to get the page size if it hasn't been set
		# in config. This is generally a waste of time, so
		# should be set in config.
		#

		if (!$GLOBALS['cfg']['sizelimit_page_size']){

			$page_size = intval(exec('getconf PAGESIZE'));

			$GLOBALS['cfg']['sizelimit_page_size'] = $page_size;

			if (!$GLOBALS['cfg']['sizelimit_page_size']) return array();
		}

		
		#
		# Read process memory statistics from /proc/self/statm (Linux only)
		#

		$lines = @file('/proc/self/statm');
		$bits = explode(' ', $lines[0]);

		return array(
			'virtual'	=> intval($bits[0]) * $GLOBALS['cfg']['sizelimit_page_size'],
			'resident'	=> intval($bits[1]) * $GLOBALS['cfg']['sizelimit_page_size'],
			'shared'	=> intval($bits[2]) * $GLOBALS['cfg']['sizelimit_page_size'],
		);
	}

	############################################################

	function sizelimit_format($s){

		$units = array('bytes','KB','MB','GB');
		while (count($units)>1 && $s > 1024){
			$s /= 1024;
			array_shift($units);
		}

		return number_format($s, 1).' '.$units[0];
	}

	############################################################

	function sizelimit_terminate(){

		#
		# best choice is apache_child_terminate() for Apache 1.x
		#

		if (function_exists('apache_child_terminate')){
			if (apache_child_terminate()) return;
		}


		#
		# if we have posix_kill(), use that
		#

		if (function_exists('posix_kill')){
			posix_kill(getmypid(), 30);
			return;
		}


		#
		# if all else fails, exec kill
		#

		$pid = getmypid();
		@exec("kill -USR1 $pid");
	}

	############################################################
