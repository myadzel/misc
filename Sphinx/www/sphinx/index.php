<?php

	ob_start();
	
	$output = ob_get_contents();
	
	ini_set("zlib.output_compression", "1");
	
	ob_end_clean();
	
	error_reporting(E_ALL | E_STRICT);
	
	require_once "config.php";
	require_once "classes/helper.php";
	
	require_once "phpmorphy/src/common.php";
	require_once "sphinxapi-0.9.9.php";
	
	if (!isset($_ENV["APACHE_RUN_USER"])) {
		parse_str($_SERVER["QUERY_STRING"], $_REQUEST);
	}
	
	$_REQUEST["q"] = isset($_REQUEST["q"]) ? trim($_REQUEST["q"]) : null;
	$_REQUEST["q"] = preg_replace("/ё/i", "е", $_REQUEST["q"]);
	
	
	//phpmorphy normalizer start
	try {
		$opts = array(
			"storage" => PHPMORPHY_STORAGE_FILE,
			"with_gramtab" => false,
			"predict_by_suffix" => true, 
			"predict_by_db" => true
		);
		
		$dict_bundle = new phpMorphy_FilesBundle("phpmorphy/dicts", "rus");
		
		$morphy = new phpMorphy($dict_bundle, $opts);
	} catch (phpMorphy_Exception $e) {
		//do nothing
	}
	
	$words = isset($_REQUEST["q"]) ? trim($_REQUEST["q"]) : "";
	$words = preg_split("/\s+/", $words);
	
	if (sizeof($words) == 1) {
		$base_list = array();
		
		foreach ($words as $word) {
			if ($forms = $morphy->getAllForms(Helper::upper($word))) {
				foreach ($forms as $form) {
					$bases = $morphy->getBaseForm($form);
					foreach ($bases as $base) {
						array_push($base_list, $base);
					}
				}
			}
		}
		
		$base_list = join("|", array_unique($base_list));
		
		if ($base_list != "") {
			$_REQUEST["q"] = $base_list;
		}
	}
	//phpmorphy normalizer end
	
	
	Helper::stripslashesRequest();
	
	$query = isset($_REQUEST["q"]) ? $_REQUEST["q"] : null;
	$index = (isset($_REQUEST["i"]) && (int) $_REQUEST["i"] > 1) ? (int) $_REQUEST["i"] : 1;
	$current_page = (isset($_REQUEST["p"]) && (int) $_REQUEST["p"] > 1) ? (int) $_REQUEST["p"] : 1;
	
	$page_size = $config["pagesize"];
	
	$indexes = array(
		1 => array(
				"index" => "teletype_works",
				"table" => "works",
				"class" => "works",
				"fields" => array("fulltitle as title", "description", "published as created")
			),
		2 => array(
				"index" => "teletype_vacancies",
				"table" => "vacancies",
				"class" => "vacancies",
				"fields" => array("fulltitle as title", "description", "published as created")
			)
	);
	
	assert_options(ASSERT_ACTIVE, 0);
	
	Helper::mysqlConnect($config["host"], $config["user"], $config["password"], $config["db"]);
	
	$sphinx_client = new SphinxClient();
	
	$sphinx_client->SetServer($config["sphinx_host"], $config["sphinx_port"]);
	$sphinx_client->SetLimits(($current_page * $page_size) - $page_size, $page_size);
	
	$sphinx_client->SetMatchMode(SPH_MATCH_EXTENDED2);
	$sphinx_client->SetSortMode(SPH_SORT_RELEVANCE);
	
	$sphinx_result = $sphinx_client->Query($query, $indexes[$index]["index"]);
	
	$excerpts_options = array(
		"before_match" => "<em>",
		"after_match" => "</em>",
		"chunk_separator" => "...",
		"limit" => 250,
		"around" => 3
	);
	
	$response_documents = (int) $sphinx_result["total"];
	$total_pages = ceil($response_documents / $page_size);
	
	header("Content-Type: text/xml; charset=UTF-8");
	
	echo "<response>\n";
	
	echo "\t<query>".Helper::entityEncode($query)."</query>\n";
	
	if(!$sphinx_client->_error) {
		if (isset($_GET["debug"])) {
			header("Content-Type: text/xml; charset=UTF-8");
			
			ob_clean();
			
			print_r($sphinx_result);
			
			exit;
		}
		
		echo "\t<result index=\"".$indexes[$index]["class"]."\" time=\"".$sphinx_result["time"]."\">\n";
		
		if ($response_documents > 0 && $current_page <= $total_pages) {
			$keys = array();
			
			echo "\t\t<meta count=\"$response_documents\" page=\"$current_page\" pages=\"$total_pages\" pagesize=\"$page_size\" />\n";
			
			echo "\t\t<matches>\n";
			
			if (is_array($sphinx_result["matches"]) && sizeof($sphinx_result["matches"])) {
				$start = ($page_size * ($current_page - 1)) + 1;
				
				$i = $start;
				
				foreach ($sphinx_result["matches"] as $key => $value) {
					array_push($keys, $key);
					
					echo "\t\t\t<match position=\"$i\">\n";
					echo "\t\t\t\t<id>$key</id>\n";
					echo "\t\t\t\t<weight>".$value["weight"]."</weight>\n";
					
					$fields_to_select = array();
					
					foreach ($indexes[$index]["fields"] as $field) {
						array_push($fields_to_select, $field);
					}
					
					array_push($fields_to_select, "created");
					
					$query_result = mysql_query("select ".join(", ", $fields_to_select)." from ".$indexes[$index]["table"]." where id = \"$key\" limit 1");
					if ($query_result && $fetch_object = mysql_fetch_object($query_result)) {
						$fragments = $sphinx_client->BuildExcerpts(
							array($fetch_object->title, $fetch_object->description, $fetch_object->created),
							$indexes[$index]["index"],
							$query,
							$excerpts_options
						);
						
						echo "\t\t\t\t<title><![CDATA[".Helper::normalizeSpace($fragments[0])."]]></title>\n";
						echo "\t\t\t\t<description><![CDATA[".Helper::normalizeSpace($fragments[1])."]]></description>\n";
						echo "\t\t\t\t<created>".Helper::normalizeSpace($fragments[2])."</created>\n";
					}
					
					echo "\t\t\t</match>\n";
					
					$i++;
				}
			}
			
			echo "\t\t\t<keys>".join(",", $keys)."</keys>\n";
			echo "\t\t</matches>\n";
			
			if (is_array($sphinx_result["words"]) && sizeof($sphinx_result["words"])) {
				echo "\t\t<words>\n";
				
				foreach ($sphinx_result["words"] as $key => $value) {
					echo "\t\t\t<word docs=\"".$value["docs"]."\" hits=\"".$value["hits"]."\">";
					echo Helper::normalizeSpace($key);
					echo "</word>\n";
				}
				
				echo "\t\t</words>\n";
			}
		} else {
			echo "\t\t<null />\n";
		}
		
		echo "\t</result>\n";
	} else {
		echo "\t<error message=\"".Helper::entityEncode($sphinx_client->_error)."\" />\n";
	}
	
	echo "</response>";
	
	exit;
	
?>