<?php  
/*
AUTOR: Alvaro Hernan Gomez Cardozo
EMAIL: agomezcardozo@herrera.unt.edu.ar
LICENSE: GNU GENERAL PUBLIC LICENSE Version 3.

Este Script toma un archivo bibun extraido con cisis y elimina los registros hijos
parametros:
php borrahijos.php entrada.txt madres.txt hijos.txt

forma de obtención de archivo entrada.txt
comando wine i2id.exe ".\BIBUN\BIBUN" from=1 > salida.txt

formato de conversión de campo vínculado 079
formato entrada: !v079!numero de inventario
formato de salida: !v079!"^z<acceso>^a<inventario>^p<precio>^d<disponibilidad>^v<volumen ejemplar>^m<modo de adquisición>^l<biblioteca>^e<estantería>;
*/

include 'control.php';
//var_dump( $argv);

if(empty($argv[1]) || empty($argv[2])|| empty($argv[3])){
	echo "parámetros incompletos:\nphp borrarhijos.php entrada.txt madres.txt hijos.txt\n\n";
	die();
}

$entrada = $argv[1];
$madres = $argv[2];
$hijos = $argv[3];

echo "Abriendo archivo {$entrada}\n";
@$archivo_entrada = fopen($entrada, "r");
if ($archivo_entrada) {
	echo "archivo abierto correctamente\n";
} else {
	die("error al abrir {$entrada}\n");
}
//cargar estructura $registro[id][array de campos]
$lineasLeidas = 1;
$registros = array();
$linea = fgets($archivo_entrada);
while ($linea){
	if(substr($linea,0, 3) == '!ID') {
		$id = trim($linea);
		$registros[$id] = array();//!ID 00XXXX
	}else{
		$registros[$id][] = $linea;
	}
	$linea 	= fgets($archivo_entrada);
	$lineasLeidas++;
}
echo "Lineas leidas $lineasLeidas\n";
fclose($archivo_entrada);
/*para cada registro, 
	si es madre
		busco los hijos
			para cada hijo unifico y elimino el hijo
*/
$registrosMadre = array();
$registrosHijos = array();
foreach ($registros as $id => $campos) {
	$desBibl = false;//005 NIVEL DE DESCRIPCIÓN BIBLIOGRÁFICA 
	foreach ($campos as $campo) {
		if ( substr($campo,0, 6) == "!v005!"){
			$desBibl = true;
			if(strtoupper(substr($campo,6, 1)) != 'X' ) { //si es madre
				$registrosMadre[$id] = $registros[$id];
			}else{
				$registrosHijos[$id] = $registros[$id];
			}
		}
	}
	if (!$desBibl) {
		echo "REGISTRO ID $id SIN DESCRIPCION BIBLIOGRÁFICA: campo 005\n";
		echo "Se considera registro madre\n";
		$registrosMadre[$id] = $registros[$id];
	}
}
echo "registros cargados " . count($registros) . "\n";
echo "--registros madre    " . count($registrosMadre) . "\n";
echo "--registros hijo     " . count($registrosHijos) . "\n";

/*control de reg hijos que a la vez contienen hijos*/
$regHijosConHijos = array();
foreach ($registrosHijos as $idH => $camposH) {
	/*control de cada registro*/
	$campo005 = '';
	foreach ($camposH as $iH => $campoH) {
		if ( substr($campoH,0, 6) == "!v005!"){
			$campo005 = $campoH;
		}
		if ( substr($campoH,0, 6) == "!v079!"){
			echo "----> REGISTRO HIJO {$idH} CONTIENE HIJOS {$campoH}: $campo005<---- \n";
			$regHijosConHijos[$idH] = $campoH;
		}
	}
}
if (count($regHijosConHijos)>0) {
	//print_r($registrosHijos);
	die("control de Registros hijos con hijos falló\n");
}
/*vinculación*/
echo "INICIANDO VINCULACIÓN DE MADRES A HIJOS\n";
foreach ($registrosMadre as $idM => $camposM) {
	foreach ($camposM as $iM => $campoM) {
		if (substr($campoM,0,6)=="!v079!" && (strpos($campoM, "^")===false) ) { //tiene hijos y no están unificados
			$invHijo = trim(substr($campoM,6,8));
			echo "madre id{$idM} tiene hijo inv:{$invHijo} no unificado, Buscando hijo->";
			$hijo_encontrado = false;
			//busco hijo, unifico y borro
			foreach ($registrosHijos as $idH => $camposH) {
				$regTemp = array();
				foreach ($camposH as $iH => $campoH) {
					switch (substr($campoH,0,6)) {
						case '!v001!':
							$regTemp['z'] = "^z" . trim(substr($campoH,6));//acceso
							break;
						case '!v077!':
							$regTemp['a'] = "^a" . trim(substr($campoH,6));//inventario
							break;
						case '!v085!':
							$regTemp['d'] = "^d" . trim(substr($campoH,6));//disponibilidad
							break;
						case '!v095!':
							if (trim(str_replace("$", '', substr($campoH,6)) )) {
								$regTemp['p'] = "^p" . trim(str_replace("$", '', substr($campoH,6)) );//precio
							}
							break;
						case '!v078!':
							$regTemp['v'] = "^v" . trim(substr($campoH,6));//volumen ejemplar
							break;
						case '!v083!':
							$regTemp['m'] = "^m" . trim(substr($campoH,8));//modo de adquisición
							break;
						case '!v017!':
							$regTemp['l'] = "^l" . trim(substr($campoH,6));//biblioteca
							break;
						case '!v002!':
							$regTemp['e'] = "^e" . trim(substr($campoH,6));//estantería
							break;
					}
				}
				if (isset($regTemp['a'])) {
					$busca_por_inventario = true; //true = busca por inv - false = busca por acceso
					if ($busca_por_inventario) {
						$encontrado = $regTemp['a'] == "^a".$invHijo;
					} else { //busca por acceso
						$encontrado = $regTemp['z'] == "^z".$invHijo;
					}
					
					if ($encontrado) {//hijo encontrado
						echo "reg Hijo {$idH} incorporado a Madre {$idM}:" . implode("",$regTemp) . "\n";
						$registrosMadre[$idM][$iM] = "!v079!" . implode("",$regTemp) . "\n";
						unset($registrosHijos[$idH]);
						$hijo_encontrado = true;
					}
				}else{
					echo "registro sin inv (v077): reg hijo id {$idH}\n";
				}
			}
			if (!$hijo_encontrado) {
				echo "Registro hijo no encontrado\n";
			}
		}
	}
}
echo "\nINICIANDO VINCULACIÓN DE HIJOS A MADRES\n";
foreach ($registrosHijos as $idH => $camposH) {
	$regTemp = array();
	$madre = '';
	foreach ($camposH as $iH => $campoH) {
		switch (substr($campoH,0,6)) {
			case '!v001!':
				$regTemp['z'] = "^z" . trim(substr($campoH,6));//acceso
				break;
			case '!v005!':
				$madre = trim(substr($campoH,7));//acceso, sin la x
				break;
			case '!v077!':
				$regTemp['a'] = "^a" . trim(substr($campoH,6));//inventario
				break;
			case '!v085!':
				$regTemp['d'] = "^d" . trim(substr($campoH,6));//disponibilidad
				break;
			case '!v095!':
				if (trim(str_replace("$", '', substr($campoH,6)) )) {
					$regTemp['p'] = "^p" . trim(str_replace("$", '', substr($campoH,6)) );//precio
				}
				break;
			case '!v078!':
				$regTemp['v'] = "^v" . trim(substr($campoH,6));//volumen ejemplar
				break;
			case '!v083!':
				$regTemp['m'] = "^m" . trim(substr($campoH,6));//modo de adquisición
				break;
			case '!v017!':
				$regTemp['l'] = "^l" . trim(substr($campoH,6));//biblioteca
				break;
			case '!v002!':
				$regTemp['e'] = "^e" . trim(substr($campoH,6));//estantería
				break;
		}
	}
	$regTemp = implode("",$regTemp);
	echo "!v001!{$madre} -> {$idH} \n";
	foreach ($registrosMadre as $idM => $camposM) {
		//busco la madre para insertar el hijo
		foreach ($camposM as $iM => $campoM) {
			if ("!v001!{$madre}" == trim($campoM) ) {
				echo "MADRE ENCONTRADA: M:{$madre} -> H:{$regTemp} \n";
				$registrosMadre[$idM][] = "!v079!" . $regTemp . "\n";
				unset($registrosHijos[$idH]);
			}
		}
	}
}

@$archivo_salida = fopen($madres, "w");
if ($archivo_salida){
	foreach ($registrosMadre as $idM => $camposM) {
		fwrite($archivo_salida, $idM . "\n");
		foreach ($camposM as $campoM) {
			fwrite($archivo_salida, $campoM);
		}
	}

	fclose($archivo_salida);
} else {
	die("error al abrir {$madres}\n");
}

@$archivo_salida = fopen($hijos, "w");
if ($archivo_salida){
	foreach ($registrosHijos as $idH => $camposH) {
		fwrite($archivo_salida, $idH . "\n");
		foreach ($camposH as $campoH) {
			fwrite($archivo_salida, $campoH);
		}
	}

	fclose($archivo_salida);
} else {
	die("error al abrir {$hijos}\n");
}


?>