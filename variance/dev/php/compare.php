<?php

include 'function/function.trimArray.php';
include 'function/function.truncateString2.php';
include 'function/function.alternateMerge.php';

include 'class/class.XMLManipulator.php';

$text_manipulator 			= new TextManipulator();

$file 						= 'txts/ae/ae_32_33/ae_32_33.txt';
$file_name 					= $text_manipulator->getFileName( $file );

$text_manipulator			->setFile( $file );
$txt_file_as_string 		= $text_manipulator->readFile			( 'as_string' );
$chars_words_counting 		= $text_manipulator->countCharsAndWords ( $txt_file_as_string );
$chars_counting 			= $text_manipulator->formatNumber		( $chars_words_counting['chars'] );
$words_counting 			= $text_manipulator->formatNumber		( $chars_words_counting['words'] );

$text_manipulator			->setFile( $file );
$txt_file_as_array  		= $text_manipulator->readFile	  ( 'as_array' );
$lines_counting 			= $text_manipulator->countLines	  ( $txt_file_as_array );
$formatted_lines_counting 	= $text_manipulator->formatNumber ( $lines_counting );


$xml_manipulator 			= new XMLManipulator();
$xml_file 					= 'xmls/ae/ae_32_33/ae_32_33.xml';
$xml_file_name 				= $xml_manipulator->getXMLFileName ( $xml_file );
$xml_manipulator			->openXMLFile( $xml_file );
$xml 						= simplexml_load_file($xml_file);
$insertions 				= ($xml->informations->transformations->insertions->ins);
$suppressions 				= ($xml->informations->transformations->suppressions->sup);
$remplacements 				= ($xml->informations->transformations->remplacements->remp);
$blocs_communs 				= ($xml->informations->transformations->blocscommuns->bc);
$deplacements 				= ($xml->informations->transformations->deplacements->bd);

$nbr_insertions    			= sizeof($insertions);
$nbr_suppressions  			= sizeof($suppressions);
$nbr_remplacements 			= sizeof($remplacements);
$nbr_remplacements_par_2	= sizeof($remplacements) / 2;
$nbr_blocs_communs 			= sizeof($blocs_communs) / 2;
$nbr_deplacements  			= sizeof($deplacements)  / 2;

$i = 0;
$s = 0;
$r = 0;
$c = 0;
$d = 0;

$text_window 				= 'c';
$text_window_2 				= 'd';

foreach ( $insertions  	 as $k )  { $insertions_tab[] 		.= $k['d'].'-'.$k['f'].'-i-#i_'.sprintf( "%05d", $i++ ) ;}
foreach ( $suppressions  as $k )  { $suppressions_tab[] 	.= $k['d'].'-'.$k['f'].'-s-#s_'.sprintf( "%05d", $s++ ) ;}
foreach ( $remplacements as $k )  { $remplacements_tab[] 	.= $k['d'].'-'.$k['f'].'-r-#r_'.sprintf( "%05d", $r++ ) ;}
foreach ( $blocs_communs as $k )  {
		
	$blocs_communs_tab[] 	.= $k['d'].'-'.$k['f'].'-c-#'.$text_window.'_'.sprintf( "%05d", $c++ );
	if ($c == $nbr_blocs_communs) { $c = 0 ; $text_window = 'b';}	
}

if($nbr_deplacements%2 != 0) {
	  array_pop($deplacements);
}

foreach ($deplacements as $k) 	{
	$deplacements_tab[] 	.= $k['d'].'-'.$k['f'].'-d-#'.$text_window_2.'_'.sprintf( "%05d", $d++ );
	if ($d == $nbr_deplacements) { $d = 0 ; $text_window_2 = 'e';}

}

$transformations_array = array_merge($insertions_tab, $suppressions_tab, $remplacements_tab, $blocs_communs_tab, $deplacements_tab);

natsort($transformations_array); // re-order
$transformations_array = array_values($transformations_array); // re-index

foreach ($transformations_array as $transformation) {
    
	$transform = explode("-", $transformation);
	
	////////// $transform['0'] = start // $transform['1'] = end // $transform['2'] = type  // $transform['3'] = anchor_id 		
   
   $length = abs($transform['0']-$transform['1']);
   $handle = fopen($file, 'r+');
   
   $debut = file_get_contents( $file, NULL, NULL, 0, (int) $transform['0'] + (int) $offset );
   $trans = file_get_contents( $file, NULL, NULL, (int) $transform['0'] + (int) $offset, (int) $length);
   $fin   = file_get_contents( $file, NULL, NULL, (int) $transform['1'] + (int) $offset, 1000000);
	
	
   switch ($transform['2']) {
	
   		case 'i':
   		$add_span = '<span class="span_i" id="'.substr($transform['3'],1).'">'.$trans.'</span>'; // 41 chars
   		break;

   		case 's':
   		$add_span = '<span class="span_s" id="'.substr($transform['3'],1).'">'.$trans.'</span>';
   		break;   
                                            
   		case 'r':                                            
   		$add_span = '<span class="span_r" id="'.substr($transform['3'],1).'">'.$trans.'</span>'; 
   		break;     
                                          
   		case 'c':                                            
   		$add_span = '<span class="span_c" id="'.substr($transform['3'],1).'">'.$trans.'</span>'; 
   		break;
        
		case 'd':                                            
   		$add_span = '<span class="span_d" id="'.substr($transform['3'],1).'">'.$trans.'</span>'; 
   		break;
   	    
   }
   
   
   $offset += 41;
   $written = $debut.$add_span.$fin;
   fwrite($handle, $written);
			

}

?>