<?php
function truncateString2($string,$length) {
	if(strlen($string) > $length) { 
		 //$str = substr($string, 0, $length);      
		$string = substr($text, 0, strrpos($text,' ', $length - strlen($text)-3)) . '...';
		 //return substr($text, 0, strrpos($text,'&#182;', $length - strlen($text)-3)) . '<span class="light">...</span>';   
		return $string;   
	} else { 
		return $string;
	}
}
?>