<?php  
function searchreplace($line, $vervose=false, $replace_file = 'config/replaceFACE.php'){	
	include $replace_file;
	$rep = $line;
	
	foreach ($repArr as $search => $replace) {
		$rep = str_replace($search, $replace, $rep);
	}
	
	
	if ( $vervose && ($line != $rep) ) {
		echo $line . " reemplazado por " . $rep . "\n";
	}
	return $rep;
}

?>
