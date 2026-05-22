<?php

	function alternateMerge($arr1, $arr2) {
	    $res=array();
	    $arr1=array_reverse($arr1);
	    $arr2=array_reverse($arr2);
	    foreach ($arr1 as $a1) {
	        if (count($arr1)==0) {
	            break;
	        }
	        array_push($res, array_pop($arr1));
	        if (count($arr2)!=0) {
	            array_push($res, array_pop($arr2));
	        }
	    }
	    return array_merge($res, $arr2);
	}

?>