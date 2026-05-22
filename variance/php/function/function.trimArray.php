<?php
function trimArray(array $array, $int){
           $newArray = array();
           for($i=0; $i<$int; $i++){
               array_push($newArray,$array[$i]);
           }
           return (array)$newArray;
       }
?>