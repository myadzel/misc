#!/usr/bin/php
<?php

	ob_start();
	
	$output = ob_get_contents();
	
	ini_set("zlib.output_compression", "1");
	
	ob_end_clean();
	
	if (!isset($_ENV["APACHE_RUN_USER"])) {
		require_once "index.php";
	}
	
?>