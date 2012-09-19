<?php

	class Helper {
	
		public static function upper($s) {
			return strtr($s, "éöóêåíãøùçõúôûâàïğîëäæıÿ÷ñìèòüáş¸", "ÉÖÓÊÅÍÃØÙÇÕÚÔÛÂÀÏĞÎËÄÆİß×ÑÌÈÒÜÁŞ¨");
		}
		
		public static function mysqlConnect($host, $user, $pass, $db) {
			$link = mysql_pconnect($host, $user, $pass);
			
			if (!$link) {
				return false;
			} else if (!mysql_select_db($db)) {
				return false;
			}
			
			mysql_query("SET NAMES utf8");
			
			return $link;
		}
			
		public static function normalizeSpace($value) {
			return trim(preg_replace("@\s+@", " ", $value));
		}
		
		public static function stripslashesRequest() {
			if (get_magic_quotes_gpc()) {
				$_REQUEST = Helper::stripslashes($_REQUEST);
			}
		}
		
		public static function stripslashes($value) {
			if (is_array($value)) {
				$converted = array();
				
				foreach ($value as $sKey => $value_new) {
					$converted[$sKey] = self::stripslashes($value_new);
				}
				
				return $converted;
			} else {
				return get_magic_quotes_gpc() ? stripslashes($value) : $value;
			}
		}
		
		public static function entityDecode($data) {
			if (is_array($data)) {
				$data_new = array();
				
				foreach ($data as $k => $v) {
					$data_new[$k] = self::entityDecode($v);
				}
				
				return $data_new;
			} else {
				return str_replace(
					array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"),
					array("&", "<", ">", "\"", "'"),
					$data
				);
			}
		}
		
		public static function entityEncode($data) {
			if (is_array($data)) {
				$data_new = array();
				
				foreach ($data as $k => $v) {
					$data_new[$k] = self::entityEncode($v);
				}
				
				return $data_new;
			} else {
				return str_replace(
					array("&", "<", ">", "\"", "'"),
					array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"),
					$data
				);
			}
		}
		
	}
	
?>