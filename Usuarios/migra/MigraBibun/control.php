<?php 
function caracteres_validos($entrada,$validos){
	$validos .= chr(10) . chr(13);
	echo "comprobando caracteres válidos: {$validos}\n";
	$validado=TRUE;
	$linea = 1;
	while (($car = fgetc($entrada))!==FALSE && $validado) {
		if (strpos($validos, $car)===FALSE) {
			echo "caracter invalido: {$car}\nASCII: " . ord($car) . "\nlinea: {$linea}\n";
			$car = fgets($entrada);
			echo "[" . $car . "]";
			$validado = FALSE;
		} 
		if (ord($car)==10) {
			$linea++;
		}
	}

	if ($validado) {
		"El archivo es válido:\n";
	}
	return $validado;
}

function contiene($campo,$valores){
	$contiene=0;
	foreach ($valores as $valor) {
		if(strpos($campo, $valor)!==FALSE){
			$contiene++;
		}
	}
	return $contiene;
}
function circunflejos($campo, $validos){
	/* devuelve:
	0 si no encontró ninguno, 
	pos del ultimo circunflejo controlado si son válidos, o 
	-1*pos del ultimo si no es válido*/
	$offset=0;
	while (($pos=strpos($campo,"^",$offset))!==FALSE) {
		if(!isset($campo[$pos+1])){
			return $offset;
		}
		if(isset($campo[$pos+1]) && strpos($validos, $campo[$pos+1])!==FALSE){
			$offset=$pos+1;
		}else{
			return -1*($pos+1);
		}
	}
	return $offset;
}

function campo($registro,$campo_devuelto,$formato){
	$return = false;
	switch ($formato) {
		case 'a': //devuleve un array
			$return = array();
			foreach ($registro->campos as $campo) {
				if ($campo->id == $campo_devuelto) {
					$return[] = trim($campo->valor);
				}
			}
			if (empty($return)) {
				$return = false;
			}
			return $return;
			break;
		case 'v': //devuelve un valor único
			foreach ($registro->campos as $campo) {
				if (!isset($campo->id)) {
					var_dump($campo);
					die();
				}
				if ($campo->id == $campo_devuelto) {
					return trim($campo->valor);
				}
			}
			break;
		default:
			die("Función campo requiere formato 'a' o 'v' \n");
			break;
	}
}

function subcampo($campo,$circunflejo){
	/*
	IN:	^aSubcampo 1^bSubcampo2, b
	OUT: Subcampo2
	*/
	$inicial = strpos($campo,"^".$circunflejo,0);
	if($inicial!==false){
		$inicial += 2;
		$final = strlen($campo)>$inicial? strpos($campo,"^",$inicial+1) : false;
		if($final){
			$subcampo = substr($campo,$inicial,$final-$inicial);
		}else{
			$subcampo = substr($campo,$inicial);
		}
	}else{//no tiene subcampos
		$subcampo = false;
	}
	return $subcampo;
}

function regla($reglas,$campo,$key){ //array, 0XX, string
	$r = isset($reglas['v'.$campo][$key])?$reglas['v'.$campo][$key]:'';
	return $r;
}

function control_campos($registro, &$inventarios, $reglas=array()){
	$mfn = $registro->mfn;
	//echo $mfn . "-";
	$c = array();
	foreach ($registro->campos as $campo) {
		//contar las repeticiones de cada campo
		if (isset($c[$campo->id])) {
			$c[$campo->id]++;
		}else{
			$c[$campo->id]=1;
		}
		$reg_campo = " ->Registro: " . $mfn . " campo " . $campo->id . ": ";
		
		$imprimir =  regla($reglas,$campo->id,'imprimir');
		if($imprimir && $imprimir=='1'){
			echo("Analizando campo v$campo->id:$campo->valor\n");
		}

		$reemplazar = regla($reglas,$campo->id,'reemplazar');
		if ($reemplazar) {
			$a= explode(',', $reemplazar);
			$campo->valor = str_replace($a[0], $a[1], $campo->valor);
		}

		$repetible = regla($reglas,$campo->id,'repetible');
		if($repetible !== ''){
			if ($repetible=="0" && $c[$campo->id]>1) {
				die(" Error en el registro $reg_campo:El campo v$campo->id no es repetible");
			}
		}

		$circunflejos = regla($reglas,$campo->id,'circunflejos');
		if ($circunflejos) {
			if (circunflejos($campo->valor,$circunflejos)<0) {
				echo($reg_campo . "circunflejos no válidos CORREGIR!!! debe contener $circunflejos contiene: $campo->valor\n");
			}
		}

		$longitud = regla($reglas,$campo->id,'longitud');
		if ($longitud) {
			if ( !in_array(strval(strlen($campo->valor)), explode(',', $longitud)) ) {
				echo ($reg_campo . "longitud inválida ($longitud) - valor: {$campo->valor}\n");
			}
		}

		$contiene = regla($reglas,$campo->id,'contiene');
		if ($contiene) {
			if ( !in_array(strval($campo->valor), explode(',', $contiene)) ) {
				echo ($reg_campo . "contenido no válido: $contiene - valor: {$campo->valor}\n");
			}
		}

		if ($campo->id == "077") {
			//inventarios
			if (isset($inventarios[intval($campo->valor)])) {
				echo($reg_campo . "INVENTARIO REPETIDO: {$campo->valor}, CORREGIR!!!\n");
			}else{
				$inventarios[$campo->valor]=$mfn;
			}
		}

		if (!isset($reglas["v$campo->id"])) {
			echo(" ->Registro: $mfn - v{$campo->id}:{$campo->valor}==>CAMPO NO CONTROLADO!!!!!\n");
		}
	}

}

?>