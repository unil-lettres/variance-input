<?php
function truncateString($string,$length) {
	if(strlen($string) > $length) { 
		 $str = substr($string, 0,$length);
		return $str.'<span class="light">...</span>';
	} else { 
		return $string;
	}
}
?>