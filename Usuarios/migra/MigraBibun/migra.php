<?php  
/*
AUTOR: Alvaro Hernan Gomez Cardozo
EMAIL: agomezcardozo@herrera.unt.edu.ar
LICENSE: GNU GENERAL PUBLIC LICENSE Version 3.

Este script toma un archivo txt en formato bibun extraido con cisis y migra sus campos a marcXML
parametros:
php migra.php entrada.txt salida.xml config.ini

forma de obtención de archivo entrada.txt
comando wine i2id.exe ".\BIBUN\BIBUN" from=1 > salida.txt
*/

$entrada = $argv[1];
$salida = $argv[2];
$config_file = isset($argv[3])?$argv[3]:"config/FACET.ini";

//Carga archivo de parámetros 
$parametros = parse_ini_file($config_file,true);

$caracteres_validos = $parametros['GENERAL']['caracteres_validos'];
//archivo para reemplazo de caracteres
$archivo_replace = $parametros['GENERAL']['archivo_replace'];
$archivo_temporal = $parametros['GENERAL']['archivo_temp'];
$archivo_error = $parametros['GENERAL']['archivo_error'];
$fuerza_inventario = $parametros['GENERAL']['fuerza_inventario'];
$reglas = $parametros['CONTROL'];
//print_r($reglas);
$conver = $parametros['MIGRACION'];
//print_r($conver);
$fr = fopen($archivo_replace, 'r');
if (!$fr) {
	die("\n ERROR: El archivo $archivo_replace no existe\n");
}else{
	fclose($fr);
}
include $archivo_replace;
include 'control.php';
include 'searchreplace.php';

if(empty($argv[1]) || empty($argv[2])){
	echo "parámetros incompletos:\nphp migra.php entrada.txt salida.xml config.ini\n\n";
	die();
}

echo "Abriendo archivo {$entrada} ";
@$archivo_entrada = fopen($entrada, "r");
if ($archivo_entrada) {
	echo " -> CORRECTO\n";
} else {
	die(" -> ERROR\n");
}
/*Reemplaza caracteres mal migrados y corrije erres en la base*/
@$archivo_temp = fopen($archivo_temporal, "w");
if ($archivo_temp){
	$i = 0;
	echo ">>Reemplazando caracteres mal migrados de DOS a UTF-8, ESPERE";
	while ($linea = fgets($archivo_entrada)) {
		$linea = searchreplace($linea, false, $archivo_replace);
		fputs($archivo_temp,$linea);
		if ($i>3000) {
			echo " ."; $i=0;
		} else {
			$i++;
		}
	}
	fclose($archivo_entrada);
	fclose($archivo_temp);
	$archivo_entrada = fopen($archivo_temporal,"r");
	echo "\nReemplazo terminado\n";
} else {
	die("error al abrir temporal\n");
}
/*controla caracteres válidos*/
$validado = caracteres_validos($archivo_entrada,$caracteres_validos);

if ($validado) {
	echo "Migrando BIBUN -> MarcXML:\n";
	
	echo "Archivo de texto a estructura\n";//registros = array( registro{mfn, campos=array ( campo{id, valor} ) } )
	fseek($archivo_entrada, 0);
	$linea = fgets($archivo_entrada);
	$registros = array();
	$campos_para_control = array();
	while ($linea) {
		switch (substr($linea,0, 3)) {
				case '!ID':
					//nuevo registro, guardo el anterior y genero uno nuevo
					if(isset($registro)){
						//var_dump($registro);
						$registros[] = $registro;
					}
					$registro = new stdClass();
					$registro->mfn = trim(substr($linea, 4));
					$registro->campos = array(); //array de objeto campo con dos elementos id y valor
					$campo = new stdClass();
					break;
				case '!v0':
				case '!v1':
				case '!v9':
					//nuevo campo
					$campo = new stdClass();
					$campo->id = substr($linea, 2,3);
					$campo->valor = trim(substr($linea, 6));
					$registro->campos[]=$campo;
					$campos_para_control[substr($linea, 2,3)] = substr($linea, 2,3);
					break;
				default:
					//continua el campo anterior
					$registro->campos[count($registro->campos)-1]->valor .= "\n" . trim($linea);
					break;
			}
		$linea 	= fgets($archivo_entrada);
	}
	if(isset($registro)){//guardo el ultimo registro generado
		$registros[] = $registro;
	}
	print( "\n*************************************\n");
	printf("  %d leidos, ultimo registro: %d  ",count($registros), $registro->mfn );
	print( "\n*************************************\n");

	$mfns = array();

	echo "Borrar registros vacíos inválidos, regla de control: sin inventario\n";
	//no migrables: nomigrados.txt
	@$archivo_error_ = fopen($archivo_error, "w");
	if ($archivo_error_){
		foreach ($registros as $i => $reg){
			$borrado = false;
			$acceso = campo($reg,'001','v');//001 NUMERO DE ACCESO
			$inventario = campo($reg,'077','v');
			$estado = campo($reg,'004','v');
			if ($estado == "LIBRE") {
				fputs($archivo_error_, "$reg->mfn -> LIBRE\n");
				unset($registros[$i]);
				$borrado = true;
			}
			if ($acceso=="" && !$borrado) {
				fputs($archivo_error_, "$reg->mfn -> sin nro acceso\n");
				unset($registros[$i]);
				$borrado = true;
				echo "Borrando mfn: $reg->mfn\n";
			}
			if ($inventario=="" && !$borrado) {
				if ($fuerza_inventario) {
					$inventario_creado = $parametros['GENERAL']['marc040a'] . $registros[$i]->mfn;
					echo "No existe inventario para registro {$registros[$i]->mfn}, creando {$inventario_creado}\n";
					$campo = new stdClass();
					$campo->id = '077';
					$campo->valor = $inventario_creado;
					$registros[$i]->campos[]=$campo;
				} else {
					fputs($archivo_error_, "$reg->mfn -> sin inventario\n");
					unset($registros[$i]);
					echo "Borrando mfn: $reg->mfn\n";
				}
			}
		}
		fclose($archivo_error_);
	} else {
		die("error al abrir $archivo_error\n");
	}
	echo "Ver archivo $archivo_error para ver lo registros inválidos\n";
	echo "\n*************************************\n\n";

	echo "validacion de campos BIBUN:\n";
	$inventarios=array();
	foreach ($registros as $reg) {
		foreach ($reg->campos as $i=>$campo) {
			//eliminar campos vacios
			if(!trim(str_replace(array("^a","^b","^c","^d","^e","^f","^g","^h","^i","^j","^k","^l","^m","^n","^o","^p","^q","^r","^s","^t","^u","^v","^w","^x","^y","^z"),"", $campo->valor))){
				unset($reg->campos[$i]);
			}
		}
		$mfns[intval($reg->mfn)] = $reg->mfn;
		control_campos($reg,$inventarios,$reglas);
	}

	echo "control de nro de acceso e inventarios repetidos\n";
	$accesos = array();
	$inventarios = array();
	foreach ($registros as $i => $reg){
		$acceso = campo($reg,'001','v');//001 NUMERO DE ACCESO
		$inventario = campo($reg,'077','v');
		$hijos = campo($reg,'079','a');
		if (isset($inventarios[$inventario]) && $inventarios[$inventario] ) {
			echo "numero de inventario repetido: mfn:{$reg->mfn} inventario {$inventarios[$inventario]}\n";
		}else{
			$inventarios[$inventario] = $inventario;
		}
		if ( !empty($hijos) ) {
			foreach ($hijos as $hijo) {
				$inv_hijo = subcampo($hijo,'a');
				if (isset($inventarios[$inv_hijo]) && $inventarios[$inv_hijo] ) {
					echo "numero de inventario repetido: mfn:{$reg->mfn} inventario {$inventarios[$inv_hijo]}\n";
				}else{
					$inventarios[$inv_hijo] = $inv_hijo;
				}
			}
		}
		//accesos
		if (isset($accesos[$acceso])) {
			echo "numero de acceso repetido: mfn:{$reg->mfn} acceso {$accesos[$acceso]}\n";
		}else{
			$accesos[$acceso] = $acceso;
		}
	}

	echo "\n*************************************\n";
	echo "FIN de validacion de campos LEER LOG\n";
	echo "\n*************************************\n";

	//var_dump($registro);

	if (false) { //control de continuidad
		echo "controlando continuidad:\n";
		for($i=1;$i<=$registro->mfn;$i++){
			if(!isset($mfns[intval($i)])){
				echo "    El mfn " . $i . " no está en la base;\n";
			}
		}
		echo "continuidad controlada\n\n";
	}
	echo "registros procesados: " . count($registros);
	echo "\n>>generando marcXML\n\n";
	$salto = "\n";
	$cabecera = '<?xml version="1.0" encoding="UTF-8" ?><marc:collection xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">'.$salto;
	$pie = '</marc:collection>';
	$cuerpo = '';
	foreach ($registros as $i => $registro) {
		echo "generando registro $registro->mfn\n";
		$cuerpo .= registro_marcXML($registro,$parametros['GENERAL']).$salto;
	}

	$texto_xml = $cabecera . $cuerpo . $pie;
	
	@$archivo_salida = fopen($salida, "w");
	if ($archivo_salida){
		fwrite($archivo_salida, $texto_xml);
		fclose($archivo_salida);
	} else {
		die("error al abrir {$salida}\n");
	}
}


function registro_marcXML($registro,$config){	// registro{mfn, campos=array ( campo{id, valor} ) }
	$r = new stdClass();
	$r->mfn = $registro->mfn;
	$r->campos = array();
	foreach ($registro->campos as $i => $campo) { //$r = {mfn, campos = array( ) }
		$r->campos["v$campo->id"][]=$campo->valor;
	}

	$tipo_item = "b";
	$salto = "\n";
	$tamanio = 1000; //generar por sistema debe calcularse a partir de los datos generados por el sistema
	$pos_base = 260; //generar por sistema debe calcularse a partir del ultimo leader
	$mfn = $registro->mfn;

	$cabecera = leader($tamanio,$pos_base);

	$xml = '';
	$control = array();
	$datos   = array();

	$control["005"] = date("YmdHis"); //fecha ultimo acceso
	$datos["040"][$mfn] = new stdClass();	$datos['040'][$mfn]->i1 = ' '; $datos['040'][$mfn]->i2 = ' ';
	$datos["040"][$mfn]->a = $config['marc040a'];
	$datos["040"][$mfn]->b = $config['marc040b'];

	$fecha_alta = "00000000";
	$entrada_principal=isset($r->campos['v009'])?$r->campos['v009'][0]:"PE";//009 INDICADOR DE PROCESAMIENTO - Autor personal PE, Responsable institucional IN, Entrada por título TI, Jurisdicción JU
	$estado_registro=isset($r->campos['v004'])?$r->campos['v004'][0]:"C";//004 ESTADO DEL REGISTRO: FC (falta clasificar) FR (falta resumen) C (completo)
	$tipo_documento=isset($r->campos['v007'])?$r->campos['v007'][0]:"TEXTO";// TIPO DE DOCUMENTO

	$datos['942'][$i] = new stdClass();	$datos['942'][$i]->i1 = ' ';$datos['942'][$i]->i2 = '';//item type
	switch (strtoupper($tipo_documento)) {
		case 'TEXTO':
			$datos['942'][$i]->c = "BK";
			$tipo_item = "b";
			break;
		case 'PELÍCULA':
		case 'VIDEOGRABACIÓN':
			$datos['942'][$i]->c = "VID";
			$tipo_item = "q";
			break;
		case 'REVISTA':
			$datos['942'][$i]->c = "CR";
			break;
		case 'FOLLETO':
			$datos['942'][$i]->c = "FOL";
			break;
		case 'TESIS':
			$datos['942'][$i]->c = "TES";
			$tipo_item = "m";
			break;
		case 'ANUARIO':
			$datos['942'][$i]->c = "AN";
			break;
		case 'CATALOGO':
			$datos['942'][$i]->c = "CAT";
			break;
		case 'APUNTE':
			$datos['942'][$i]->c = "AP";
			break;
		case 'INFORME':
			$datos['942'][$i]->c = "INF";
			break;
		case 'SONORO':
			$datos['942'][$i]->c = "SON";
			break;
		default:
			$datos['942'][$i]->c = "S/E";
			$datos['500'][$i] = new stdClass();	$datos['500'][$i]->i1 = ' ';$datos['500'][$i]->i2 = ' ';
			$datos['500'][$i]->a = "tipo de material sin especificar: {$campo->valor}";
			break;
		}

	$desc_bibliográfica=isset($r->campos['v005'])?$r->campos['v005'][0]:"m";// NIVEL DE DESCRIPCIÓN BIBLIOGRÁFICA
	$nivel_referencia  =isset($r->campos['v006'])?$r->campos['v006'][0]:"";//NIVEL DE REFERENCIA
	if (strtoupper($desc_bibliográfica) == 'S') {
		$cabecera = leader($tamanio,$pos_base,'s');
	}

	$soporte=isset($r->campos['v008'])?$r->campos['v008'][0]:"";// 008: NIVEL DE DESCRIPCIÓN BIBLIOGRÁFICA
	$datos['338'][$i] = new stdClass();	$datos['338'][$i]->i1 = ' ';$datos['338'][$i]->i2 = ' ';
	$datos['338'][$i]->a = strtolower($soporte);

	$control['001'] = isset($r->campos['v098'])?$r->campos['v098'][0]:"";// Nro de control
	$control['003'] = isset($r->campos['v076'])?$r->campos['v076'][0]:"";// Identificador del número de control

	$fechas = isset($r->campos['v003'])?$r->campos['v003'][0]:"";
	$fecha_alta = subcampo($fechas,'a');
	$fecha = $fecha_alta?$fecha_alta:"0000 00 00";
	$aa = substr($fecha,0,4);
	$mm = substr($fecha,5,2);
	$dd = substr($fecha,8,2);
	$fecha_alta = $aa.$mm.$dd;
	$datos['046'][$i] = new stdClass();	$datos['046'][$i]->i1 = ' ';$datos['046'][$i]->i2 = ' ';
	if ( subcampo($fecha, 'm') ) { $datos['046'][$i]->a =  subcampo($fechas, 'm'); }
	if ( subcampo($fecha, 'b') ) { $datos['046'][$i]->a =  subcampo($fechas, 'b'); }

	foreach ($registro->campos as $i => $campo) {
		if (!isset($campo->id)) {
			echo "id no definido campo:" . json_encode($campo);
		}
		switch ($campo->id) {
				case '098'://Código de control
				case '076'://BIBLIOTECA DEPOSITARIA
				case '003'://FECHA DE ALTA, MODIFICACIÓN O BAJA
				case '001': //NUMERO DE ACCESO
				case '005'://005 NIVEL DE DESCRIPCIÓN BIBLIOGRÁFICA 
				case '006'://006 NIVEL DE REFERENCIA - m si es monográfico hijo, s si es monografico de una publicacion seriada
				case '007'://007 TIPO DE DOCUMENTO
				case '008'://008 SOPORTE DEL DOCUMENTO - "PAPEL", "CD", "CD-ROM", "DVD", "VHS", "CINTMAG", "DISCO", "VIDEOGRA"
					break;
				case '002'://002 Repositorio o estantería
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->c = $campo->valor;
					break;
				case '011'://011 ISBN (Obra completa) - se reg: 950-9773-00-X
				case '010'://010 ISBN - Longitud 13 - ej 950-587-014-0
					if (!isset($datos['020'][$registro->mfn])) {
						$datos['020'][$registro->mfn] = new stdClass();	
						$datos['020'][$registro->mfn]->i1 = ' ';
						$datos['020'][$registro->mfn]->i2 = ' ';
					}
					if ($campo->id == '010') {
						$datos['020'][$registro->mfn]->a = str_replace("-",'', $campo->valor);
					}else{
						$datos['020'][$registro->mfn]->z = str_replace("-",'', $campo->valor);
					}
					break;
				case '015'://015 ISSN - Longitud 9 - ej 9501-5187
					$datos['022'][$i] = new stdClass();	$datos['022'][$i]->i1 = ' ';$datos['022'][$i]->i2 = ' ';
					$datos['022'][$i]->c = $campo->valor;
					break;
				case '017'://017 biblioteca
					$sede = $campo->valor;
					break;
				case '020'://020 TITULO (NIVEL ANALÍTICO) ^t Título.^s Subtítulo u otra información referente altítulo. ^r Responsabilidad asociada
				case '024'://024 TITULO (NIVEL MONOGRÁFICO)
					$datos['245'][$i] = new stdClass();	$datos['245'][$i]->i1 = '1';$datos['245'][$i]->i2 = '0';
					$datos['245'][$i]->a = subcampo($campo->valor,'t');
					$datos['245'][$i]->b = subcampo($campo->valor,'s');
					$datos['245'][$i]->c = subcampo($campo->valor,'r');
					$datos['245'][$i]->h = isset($soporte)?$soporte:'';
					if ($entrada_principal == 'TI') {
						$datos['130'][$i] = new stdClass();	$datos['130'][$i]->i1 = ' ';$datos['130'][$i]->i2 = ' ';
						$datos['130'][$i]->a = subcampo($campo->valor,'t');
					}
					break;
				case '027'://024 TITULO UNIFORME (NIVEL MONOGRÁFICO)
					$datos['240'][$i] = new stdClass();	$datos['240'][$i]->i1 = '1';$datos['240'][$i]->i2 = ' ';
					$datos['240'][$i]->a = $campo->valor;
					break;
				case '030'://030 TITULO (NIVEL COLECCIÓN)
				case '036'://036 TITULO (PUBLICACIÓN EN SERIE)
					$datos['490'][$i] = new stdClass();	$datos['490'][$i]->i1 = '0';$datos['490'][$i]->i2 = ' ';
					$datos['490'][$i]->a = subcampo($campo->valor,'t');
					if ( subcampo($campo->valor,'s') ) {
						$datos['490'][$i]->a = "(" . subcampo($campo->valor,'s') . ")";
					}
					if ($entrada_principal == 'TI') {
						$datos['130'][$i] = new stdClass();	$datos['130'][$i]->i1 = ' ';$datos['130'][$i]->i2 = ' ';
						$datos['130'][$i]->a = subcampo($campo->valor,'t');
					}
					if (subcampo($campo->valor,'u')) {//subserie
						$datos['490'][$i.'1'] = new stdClass();	$datos['490'][$i.'1']->i1 = '0';$datos['490'][$i.'1']->i2 = ' ';
						$datos['490'][$i.'1']->a = subcampo($campo->valor,'u'); 
					}
					break;
				case '022'://022 AUTOR PERSONAL (NIVEL ANALÍTICO) ^aApell. ^bNom o iniciales.^dFecha de nac. y muerte.^oOtros nombres: seudónimos,^f Función.
				case '028'://028 AUTOR PERSONAL (NIVEL MONOGRÁFICO)
					$datos['245'][$i] = new stdClass();	$datos['245'][$i]->i1 = '1';$datos['245'][$i]->i2 = '0';
					$campoAutorPersonal =  subcampo($campo->valor,'a') . ", " . subcampo($campo->valor,'b');
					if (subcampo($campo->valor,'f')) {
						$campoAutorPersonal = subcampo($campo->valor,'f') . ": " . $campoAutorPersonal;
					}
					if (subcampo($campo->valor,'o')) {
						$campoAutorPersonal .= "(" . subcampo($campo->valor,'o') . ")";
					}
					if (subcampo($campo->valor,'d')) {
						$campoAutorPersonal .= "(" . subcampo($campo->valor,'d') . ")";
					}
					if ($entrada_principal == 'PE' && !isset($datos['100'])) {
						$datos['100'][$i] = new stdClass();	$datos['100'][$i]->i1 = ' ';$datos['100'][$i]->i2 = ' ';
						$datos['100'][$i]->a = subcampo($campo->valor,'a')." ".subcampo($campo->valor,'b'); 
					}
					$datos['245'][$i]->c = $campoAutorPersonal;
					break;
				case '033'://033 AUTOR PERSONAL (NIVEL COLECCIÓN)
					if ($entrada_principal == 'PE') {
						$datos['100'][$i] = new stdClass();	$datos['100'][$i]->i1 = ' ';$datos['100'][$i]->i2 = ' ';
						$datos['100'][$i]->a = subcampo($campo->valor,'a')." ".subcampo($campo->valor,'b'); 
					}
					$datos['508'][$i] = new stdClass();	$datos['508'][$i]->i1 = ' ';$datos['508'][$i]->i2 = ' ';
					if ( !subcampo($campo->valor,'a') && !subcampo($campo->valor,'b') ) {
						$datos['508'][$i]->a = "[por:]" . $campo->valor; 
					}else{
						$datos['508'][$i]->a = "[por:]" . subcampo($campo->valor,'a')." ".subcampo($campo->valor,'b'); 
					}
					break;
				case '023'://RESPONSABLE INSTITUCIONAL (NIVEL ANALÍTICO) ^eNombre oficial de la entidad.^sSigla de la organización.^jEntidad de mayor
				case '029'://RESPONSABLE INSTITUCIONAL (NIVEL MONOGRÁFICO)	
				case '034'://RESPONSABLE INSTITUCIONAL (NIVEL COLECCIÓN)
					$entidad_mayor = subcampo($campo->valor,'j');
					$siglas = subcampo($campo->valor,'s')? "(".subcampo($campo->valor,'s').")" : "";
					$lugar  = subcampo($campo->valor,'l')? "(".subcampo($campo->valor,'l').")" : "";
					$pais   = subcampo($campo->valor,'p')? "(".subcampo($campo->valor,'p').")" : "";
					$datos['508'][$i] = new stdClass();	$datos['508'][$i]->i1 = ' ';$datos['508'][$i]->i2 = ' ';
					$datos['508'][$i]->a = "Responsable institucional: {$entidad_mayor} ";
					if(subcampo($campo->valor,'e')) { $datos['508'][$i]->a .= subcampo($campo->valor,'e');}
					if($siglas) { $datos['508'][$i]->a .= $siglas;}
					if($pais) { $datos['508'][$i]->a .= $pais;}
					if($lugar) { $datos['508'][$i]->a .= $lugar;}
					if ($entrada_principal == 'IN') {
						$datos['110'][$i] = new stdClass();	$datos['110'][$i]->i1 = ' ';$datos['110'][$i]->i2 = ' ';
						$datos['110'][$i]->a = $entidad_mayor?$entidad_mayor:subcampo($campo->valor,'e').$siglas; 
					}
					break;
				case '039': //039 RESPONSABLE (PUBLICACIÓN EN SERIE)
					if ($entrada_principal == 'PE') {
						$datos['100'][$i] = new stdClass();	$datos['100'][$i]->i1 = ' ';$datos['100'][$i]->i2 = ' ';
						$datos['100'][$i]->a = subcampo($campo->valor,'a')." ".subcampo($campo->valor,'b'); 
					}
					$datos['508'][$i] = new stdClass();	$datos['508'][$i]->i1 = ' ';$datos['508'][$i]->i2 = ' ';
					$datos['508'][$i]->a = "Responsable: ".$campo->valor; 
					break;
				case '040'://040 NOMBRE DE LA REUNIÓN - ^n Nombre de la reunión.^x Número, se coloca en números arábigos y sinabreviatura del ordinal.^o
					if(!isset($datos['111'][$registro->mfn]) ){
						$datos['111'][$registro->mfn] = new stdClass();	$datos['111'][$registro->mfn]->i1 = ' ';$datos['111'][$registro->mfn]->i2 = ' ';
					}
					$datos['111'][$registro->mfn]->a = subcampo($campo->valor,'a')." ".subcampo($campo->valor,'b'); 

					$datos['508'][$i] = new stdClass();	$datos['508'][$i]->i1 = ' ';$datos['508'][$i]->i2 = ' ';
					$datos['508'][$i]->a = "Responsable: ".$campo->valor; 
					break; 
				case '041'://041 LUGAR DE LA REUNIÓN - ^lLocalidad ^pPaís(CODIGO)
					if(!isset($datos['111'][$registro->mfn]) ){
						$datos['111'][$registro->mfn] = new stdClass();	$datos['111'][$registro->mfn]->i1 = ' ';$datos['111'][$registro->mfn]->i2 = ' ';
					}
					$datos['111'][$registro->mfn]->c = $campo->valor;
					break;
				case '042'://042 FECHA DE LA REUNIÓN
					if(!isset($datos['111'][$registro->mfn]) ){
						$datos['111'][$registro->mfn] = new stdClass();	$datos['111'][$registro->mfn]->i1 = ' ';$datos['111'][$registro->mfn]->i2 = ' ';
					}
					$datos['111'][$registro->mfn]->d = $campo->valor;
					break;
				case '043'://043 RESPONSABLE DE LA REUNIÓN
					if(!isset($datos['647'][$registro->mfn]) ){
						$datos['647'][$registro->mfn] = new stdClass();	$datos['647'][$registro->mfn]->i1 = ' ';$datos['647'][$registro->mfn]->i2 = ' ';
						$datos['647'][$registro->mfn]->g = '';
					}
					if (subcampo($campo->valor,'e')) {
						$datos['647'][$registro->mfn]->g .= "[Responsable:] " . subcampo($campo->valor,'e');
					}
					if (subcampo($campo->valor,'s')) {
						$datos['647'][$registro->mfn]->g .= subcampo($campo->valor,'s');
					}
					if (subcampo($campo->valor,'j')) {
						$datos['647'][$registro->mfn]->g .= "[Responsable:] " . subcampo($campo->valor,'j');
					}
					break;
				case '044'://044 EDICION
					$datos['250'][$i] = new stdClass();	$datos['250'][$i]->i1 = ' ';$datos['250'][$i]->i2 = ' ';
					$datos['250'][$i]->d = $campo->valor;
					break;
				case '045'://045 FECHA DE PUBLICACIÓN (seriada)
					if(!isset($datos['260'][$registro->mfn]) ){
						$datos['260'][$registro->mfn] = new stdClass();	$datos['260'][$registro->mfn]->i1 = ' ';$datos['260'][$registro->mfn]->i2 = ' ';
					}
					$datos['260'][$registro->mfn]->c = $campo->valor;
					break;
				case '047'://047 EDITOR Y LUGAR DE EDICIÓN - ^e Editor. Si no se conoce se indica s.n.Si aparecen más de un editor, se consignan en 
					if(!isset($datos['260'][$registro->mfn]) ){
						$datos['260'][$registro->mfn] = new stdClass();	$datos['260'][$registro->mfn]->i1 = ' ';$datos['260'][$registro->mfn]->i2 = ' ';
					}
					$datos['260'][$registro->mfn]->b = subcampo($campo->valor,'e');
					$datos['260'][$registro->mfn]->a = subcampo($campo->valor,'l');
					break;
				case '048'://048 PAÍS DE EDICIÓN - código de país
					if(!isset($datos['044'][$registro->mfn]) ){
						$datos['044'][$registro->mfn] = new stdClass();	$datos['044'][$registro->mfn]->i1 = ' ';$datos['044'][$registro->mfn]->i2 = ' ';
					}
					$datos['044'][$registro->mfn]->b = tabla_pais($campo->valor);
					break;
				case '050'://050 IDIOMA DEL DOCUMENTO
					if(!isset($datos['041'][$registro->mfn]) ){
						$datos['041'][$registro->mfn] = new stdClass();	$datos['041'][$registro->mfn]->i1 = ' ';$datos['041'][$registro->mfn]->i2 = ' ';
					}
					$datos['041'][$registro->mfn]->b = tabla_idiomas($campo->valor);
					break;
				case '052'://052 DESCRIPCIÓN FÍSICA - ^e Extensión. Páginas, volúmenes o cantidad de piezas. ^i Material ilustrativo. Ilustraciones, 
					if(!isset($datos['300'][$registro->mfn]) ){
						$datos['300'][$registro->mfn] = new stdClass();	$datos['300'][$registro->mfn]->i1 = ' ';$datos['300'][$registro->mfn]->i2 = ' ';
					}
					$datos['300'][$registro->mfn]->a = subcampo($campo->valor,'e');
					$datos['300'][$registro->mfn]->b = subcampo($campo->valor,'i') . subcampo($campo->valor,'c');

					break;
				case '054'://054 PROYECTO, PROGRAMA U OTRO ENCUADRE  - ^i Código identificador del proyecto, programa, convenio, etc.^n Nombre o sigla del 
					$datos['500'][$i] = new stdClass();	$datos['500'][$i]->i1 = ' ';$datos['500'][$i]->i2 = ' ';
					$datos['500'][$i]->a = "Proyecto: {$campo->valor}";
					break;
				case '014'://014 norma o ley, está mal usado
					$datos['500'][$i] = new stdClass();	$datos['500'][$i]->i1 = ' ';$datos['500'][$i]->i2 = ' ';
					$datos['500'][$i]->a = "$campo->valor";
					break;
				case '055'://055 TESIS - ^n Denominación de la tesis, como aparece en el documento: Tesis, Thesis, Dissertation,Mémoire de Diplome, 
					$datos['502'][$i] = new stdClass();	$datos['502'][$i]->i1 = ' ';$datos['502'][$i]->i2 = ' ';
					$datos['502'][$i]->a = subcampo($campo->valor,'n')."-".subcampo($campo->valor,'c');//nota de tesis <- denominación Y carrera
					$datos['502'][$i]->b = subcampo($campo->valor,'g');//tipo de titulo <- grado academico
					$datos['502'][$i]->c = subcampo($campo->valor,'e')." ".subcampo($campo->valor,'s');//entidad que la otorga y siglas
					$datos['502'][$i]->d = subcampo($campo->valor,'d');//año de publicación
					break;
				case '057'://057 RELACIÓN HORIZONTAL O CRONOLÓGICA ANTERIOR - ^r Tipo de relación. Edición, traducción u otra relación. ^l Lengua. Se consigna con código ISO. ^t Titulo o descripción. Tal como aparece en el documento. ^i Identificación. ISBN, ISSN, u otro código correspondiente al documento original. ^m Número de acceso. Se consigna el número de acceso, del campo 01, del registro con el que se relaciona. 
					$datos['780'][$i] = new stdClass();	$datos['780'][$i]->i1 = '0';$datos['780'][$i]->i2 = ' ';
					$datos['780'][$i]->c = subcampo($campo->valor,'r');//Información adicional <- Tipo de relación. Edición, traducción u otra relación.
					$datos['780'][$i]->e = tabla_idiomas(subcampo($campo->valor,'l'));//codigo de idioma marc <- lengua iso
					$datos['780'][$i]->t = subcampo($campo->valor,'t');
					$datos['780'][$i]->z = subcampo($campo->valor,'i');//ISBN
					//$datos['780'][$i]->w = $control['003'] . subcampo($campo->valor,'m');//nro de control <- id de cod control + nro de acceso (nro de control)
					$datos['780'][$i]->w = "SEO" . subcampo($campo->valor,'m');
					break;
				case '058'://057 RELACIÓN HORIZONTAL O CRONOLÓGICA ANTERIOR - ^r Tipo de relación. Edición, traducción u otra relación. ^l Lengua. Se consigna con código ISO. ^t Titulo o descripción. Tal como aparece en el documento. ^i Identificación. ISBN, ISSN, u otro código correspondiente al documento original. ^m Número de acceso. Se consigna el número de acceso, del campo 01, del registro con el que se relaciona. 
					$datos['785'][$i] = new stdClass();	$datos['785'][$i]->i1 = '0';$datos['785'][$i]->i2 = ' ';
					$datos['785'][$i]->c = subcampo($campo->valor,'r');//Información adicional <- Tipo de relación. Edición, traducción u otra relación.
					$datos['785'][$i]->e = tabla_idiomas(subcampo($campo->valor,'l'));//codigo de idioma marc <- lengua iso
					$datos['785'][$i]->t = subcampo($campo->valor,'t');
					$datos['785'][$i]->z = subcampo($campo->valor,'i');//ISBN
					//$datos['785'][$i]->w = $control['003'] . subcampo($campo->valor,'m');//nro de control <- id de cod control + nro de acceso (nro de control)
					$datos['785'][$i]->w = "SEO" . subcampo($campo->valor,'m');
					break;
				case '059'://059 NOTAS - ^a naturaleza y alcance ^b sistema requerido ^c manejo de derechos ^d actualidad de la información (año mes dia) ^e 
					$datos['500'][$i] = new stdClass();	$datos['500'][$i]->i1 = ' ';$datos['500'][$i]->i2 = ' ';
					$datos['500'][$i]->a = $campo->valor;
					break;
				case "060"://060 CLASIFICACIÓN TEMÁTICA - segun CDU
					if(!isset($datos['080'][$registro->mfn]) ){
						$datos['080'][$registro->mfn] = new stdClass();	$datos['080'][$registro->mfn]->i1 = ' ';$datos['080'][$registro->mfn]->i2 = ' ';
					}
					if ( !isset($datos['080'][$registro->mfn]->a) ) {
						$datos['080'][$registro->mfn]->a = $campo->valor;
					}
					break;
				case "061"://061 ENCABEZAMIENTOS DE MATERIA - Mezquitas%Templos
					$datos['650'][$i] = new stdClass();	$datos['650'][$i]->i1 = ' ';$datos['650'][$i]->i2 = '4 ';
					$datos['650'][$i]->a = $campo->valor;
					break;
				case '063'://sonónimos
				case '065'://descriptores
				case "062"://062 PALABRAS CLAVES - GRAMÍNEAS%AMERICA LATINA%CULTIVOS
				case "107"://062 PALABRAS CLAVES - GRAMÍNEAS%AMERICA LATINA%CULTIVOS
					$datos['653'][$i] = new stdClass();	$datos['653'][$i]->i1 = ' ';$datos['653'][$i]->i2 = '4 ';
					$datos['653'][$i]->a = $campo->valor;
					break;
				case "069"://069 RESUMEN
					$datos['520'][$i] = new stdClass();	$datos['520'][$i]->i1 = ' ';$datos['520'][$i]->i2 = ' ';
					$datos['520'][$i]->a = $campo->valor;
					break;
				case '075'://075 SIGNATURA TOPOGRÁFICA - ^c Signatura de clase. ^l Signatura librística y adicionales. ^u dirección electrónica IP
					$datos['080'][$i] = new stdClass();	$datos['080'][$i]->i1 = ' ';$datos['080'][$i]->i2 = ' ';
					$datos['080'][$i]->a  = subcampo($campo->valor, 'c');
					$datos['080'][$i]->_2 = subcampo($campo->valor, 'l');
					break;
				case "080"://080 EXISTENCIAS (PUBLICACIÓN EN SERIE) - se registró la disponibilidad
					$datos['852'][$i] = new stdClass();	$datos['852'][$i]->i1 = ' ';$datos['852'][$i]->i2 = ' ';
					$datos['852'][$i]->a = $campo->valor;
					break;
				case "084"://084 ACERVO DOCUMENTAL
					//$datos['500'][$i] = new stdClass();	$datos['500'][$i]->i1 = ' ';$datos['500'][$i]->i2 = ' ';
					//$datos['500'][$i]->a = "Acervo Documental: {$campo->valor}";
					break;
				case "087"://087 REPERTORIO DE INDIZACION - tesauro
					//$datos['500'][$i] = new stdClass();	$datos['500'][$i]->i1 = ' ';$datos['500'][$i]->i2 = ' ';
					//$datos['500'][$i]->a = "REPERTORIO DE INDIZACION (tesauro): {$campo->valor}";
					break;
				case "999"://999 OPERADOR
					$datos['900'][$i] = new stdClass();	$datos['900'][$i]->i1 = ' ';$datos['900'][$i]->i2 = ' ';
					$datos['900'][$i]->a = "Operador: {$campo->valor}";
					break;
				case '012'://012 CÓDIGO DOCUMENTO/NUMERO SERIE MONOGRÁFICA
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->t = $campo->valor;
					break;
				case '077'://077 INVENTARIO - debe ser unico y de 8 dígitos
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->p = $campo->valor; //str_pad($campo->valor, "0",STR_PAD_LEFT);
					$datos['952'][$registro->mfn]->i = $campo->valor; //str_pad($campo->valor, "0",STR_PAD_LEFT);
					$datos['037'][$i] = new stdClass();	$datos['037'][$i]->i1 = ' ';$datos['037'][$i]->i2 = ' ';
					$datos['037'][$i]->a = $campo->valor;
					break;
				case "078"://078 VOLUMEN Y EJEMPLAR - v.3 ej .3
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->t = $campo->valor;
					break;
				case "083"://083 FORMA DE ADQUISICIÓN
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->e = subcampo($campo->valor,'c')?subcampo($campo->valor,'c'):'';
					$datos['952'][$registro->mfn]->e .= subcampo($campo->valor,'d')?subcampo($campo->valor,'d'):'';
					$datos['952'][$registro->mfn]->e .= subcampo($campo->valor,'v')?subcampo($campo->valor,'v'):'';
					$datos['952'][$registro->mfn]->e .= subcampo($campo->valor,'r')?subcampo($campo->valor,'r'):'';
					break;
				case "085"://085 DISPONIBILIDAD
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->f = $campo->valor;
					break;
				case "095"://095 VALOR DEL DOCUMENTO
					if(!isset($datos['952'][$registro->mfn]) ){
						$datos['952'][$registro->mfn] = new stdClass();	$datos['952'][$registro->mfn]->i1 = ' ';$datos['952'][$registro->mfn]->i2 = ' ';
					}
					$datos['952'][$registro->mfn]->g = $campo->valor;
					break;
				case "079"://079 REGISTROS VINCULADOS-formato de salida: "^a".$inventario."^d".$disponibilidad."^p".$precio."^v".$volumen_ejemplar."^m".$adquisición;
						$datos['952'][$i] = new stdClass();	$datos['952'][$i]->i1 = ' ';$datos['952'][$i]->i2 = ' ';
					if ( subcampo($campo->valor,'a') ) {
						$datos['952'][$i]->p = subcampo($campo->valor,'a');
						$datos['952'][$i]->i = subcampo($campo->valor,'a');
						$datos['952'][$i]->f = subcampo($campo->valor,'d');
						$datos['952'][$i]->g = subcampo($campo->valor,'p');
						$datos['952'][$i]->t = subcampo($campo->valor,'v');
						$datos['952'][$i]->e = subcampo($campo->valor,'m');
						$datos['952'][$i]->a = subcampo($campo->valor,'l');
						$datos['952'][$i]->c = subcampo($campo->valor,'e');
					}else{
						$datos['952'][$i]->p = $campo->valor;
					}
					break;
				//control no se migra
				case "120"://campo para archivo, no se utiliza
				case '004':
				case '086'://seguridad no se migra
				case '009':
					break;
				default:
					echo "Campo {$campo->id} no migrado\n";
					break;
			}

	}
	if (isset($datos['245']) && count($datos['245'])>1) {
		$d=new stdClass();
		$d->a='';
		$d->b='';
		$d->c='';
		$d->h='';
		foreach ($datos['245'] as $i => $rep) {
			$d->a .= (isset($rep->a)&&$rep->a)? $rep->a:'';
			$d->b .= (isset($rep->b)&&$rep->b)? $rep->b:'';
			$d->c .= (isset($rep->c)&&$rep->c)? $rep->c.'; ':'';
			$d->h .= (isset($rep->h)&&$rep->h)? $rep->h:'';
			unset($datos['245'][$i]);
		}
		if ($d->b) { //tiene subtitulo
			$d->a = $d->a . ' : ';
			$d->b = $d->b . ' /';
		}else{
			$d->a = $d->a . ' /';
		}
		$datos['245'][$mfn] = $d;
	};

	$control["008"] = campo_control_008($fecha_alta,"","",$tipo_item);

	$xml .= '<marc:record>'.$salto;
	$xml .= '<marc:leader>' . $cabecera . '</marc:leader>'.$salto;
	
	ksort($control);
	ksort($datos);

	foreach($control as $tag => $val){
		$xml .= '<marc:controlfield tag="'.$tag.'">' . $val . '</marc:controlfield>'.$salto;
	}

	foreach($datos as $tag => $campo){//$campo = array ( obj obj obj )
		//var_dump($campo);
		foreach ($campo as $id => $repeticion) {//id puede ser mfn o numero de repetición - $repeticion = obj
			@$xml .= ' <marc:datafield tag="'.$tag.'"  ind1="'.$repeticion->i1.'" ind2="'.$repeticion->i2.'" >'.$salto;
			foreach ($repeticion as $code => $subcampo) {
				if ($code != "i1" && $code != "i2") {
					$xml .= '  <marc:subfield code="'.str_replace('_','',$code).'">' . $subcampo . '</marc:subfield>'.$salto;
				}
			}
			$xml .= ' </marc:datafield>'.$salto;
		}
	}

	$xml .= '</marc:record>';
	return $xml;
}

function leader($tamanio,$pos_base,$tipo='m'){
	$cabecera = str_pad($tamanio, 5, "0", STR_PAD_LEFT);
	$cabecera .= "c"; //05 - c: reg corregido
	//$cabecera .= $peli?"g":"a"; //06 - a: material textual - gmaterial gráfico proyectable
	$cabecera .= "a"; //06 - a: material textual - gmaterial gráfico proyectable
	$cabecera .= $tipo; //07 - m: monográfico - s: seriada
	$cabecera .= "#"; //08 - #: sin control especifico
	$cabecera .= " "; //09 -  : no especifica codificacion esquema de codificacion
	$cabecera .= "2"; //10 - 2: numero de indicadores, generado por el sistema
	$cabecera .= "2"; //11 -  : numero de caracteres para delimitadoes de sub campo
	$cabecera .= str_pad($pos_base, 5, "0", STR_PAD_LEFT); //12a16 -  : direccion de la posición base
	$cabecera .= "1"; //17 -  : nivel de codificacion, no especifica codificacion
	$cabecera .= "a"; //18 -  : forma de catalogacion descriptiva, AACR2R
	$cabecera .= " "; //19 -  : requerimiento de registro relacionado
	$estructura = 4500; //generado por el sistema (siempre es 4500)
	$cabecera .= str_pad($estructura, 4, "0", STR_PAD_LEFT); //20a23 -  : Estructura de las entradas del directorio
	return $cabecera;
}

function campo_control_008($fecha,$publicacion="",$ilustración="",$tipo_item="b",$idioma="ES"){
	$campo008 = $fecha;//0a5 fecha de ingreso
	$campo008 .= "|";//6 tipo de fecha |:sin fecha
	$campo008 .= "    ";// 7a10 fecha de inicio de publicacion NO APLICA
	$campo008 .= "    ";//11a14 fecha de fin  de publicacion NO APLICA
	/*REQUIERE TABLA DE CONVERSION ENTRE BIBUN Y MARC*/
	$campo008 .= str_pad($publicacion, 3, "#");//15a17 lugar de publicacion segun USMARC Code List for Countries
	$campo008 .= str_pad($ilustración, 4, "|");//18a21 ilustraciones
	$campo008 .= "|";//22 publico al que esta destinada
	$campo008 .= "|";//23 forma del item
	$campo008 .= str_pad($tipo_item, 4, " ");//24a27 naturaleza del contenido b: bibliografia, m:tesis ' ':naturaleza no especificada
	$campo008 .= "|";//28 publicación gubernamental #:no es
	$campo008 .= "|";//29 publicacion de una conferencia |:no codificada
	$campo008 .= "|";//30 homenaje |:no codificada
	$campo008 .= "|";//31 indice |:no codificada
	$campo008 .= "|";//32 posición no definida |:no codificada
	$campo008 .= "|";//33 forma literaria
	$campo008 .= "|";//34 biografia #:no contiene |:no se intenta codificar
	$campo008 .= str_pad($idioma, 4, " ");//35a37 idioma 			REQUIERE TABLA DE CONVERSION
	$campo008 .= "|";//38 registro modificado |:no codificada
	$campo008 .= "|";//39 fuente de catalogacion |:no codificada
	
	return $campo008;
}
function tabla_idiomas($cod_iso){
	switch(strtolower($cod_iso)){
				case "es": $idioma = 'spa'; break;  //español
				case "en": $idioma = 'eng'; break;  //infles
				case "fr": $idioma = 'fre'; break;  //frances
				case "de": $idioma = 'goh'; break;  //aleman
				case "el": $idioma = 'grc'; break;  //griego
				case "it": $idioma = 'ita'; break;  //italiano
				case "la": $idioma = 'lat'; break;  //latin
				case "ma": $idioma = 'hun'; break;  //hungaro
				case "pl": $idioma = 'pol'; break;  //polaco
				case "pt": $idioma = 'por'; break;  //portugues
				case "ru": $idioma = 'rum'; break;  //Ruso  ru -> romanian
				case "su": $idioma = 'fin'; break;  //finlandes -> finnish
				case "ni": $idioma = 'dut'; break; //holandés
				case "ar": $idioma = 'ara'; break; //Árabe
				case "co": $idioma = 'cos'; break; //Corso
				case "da": $idioma = 'dan'; break; //Danés
				case "fa": $idioma = 'per'; break; //Persa
				case "gn": $idioma = 'grn'; break; //Guaraní
				case "hn": $idioma = 'kor'; break; //Coreano
				case "ir": $idioma = 'gle'; break; //irlandés
				case "ku": $idioma = 'kur'; break; //Kurdo
				case "no": $idioma = 'nor'; break; //Noruego
				case "se": $idioma = 'tsn'; break; //Setswana
				case "sr": $idioma = 'srp'; break; //Serbio
				case "th": $idioma = 'tha'; break; //Tailandés
				case "tr": $idioma = 'tur'; break; //Turco
				case "zh": $idioma = 'chi'; break; //chino
				case "zu": $idioma = 'zul'; break; //Zulú
				case "cs": $idioma = "cze"; break; //checo (el código bibun para checo es igual al de español)
				default: $idioma = 'BIBUN:'.$cod_iso;
					$idioma = "iso: ".$cod_iso;
	}
	
	return $idioma;
}

function tabla_pais($cod_iso){
	$cod_iso = str_replace("/","",$cod_iso);
	switch(strtolower($cod_iso)){
				case "sin datos":
				case "s.l":
				case "s.l.": $pais = '--'; break;  //sin lugar
				case "ar": $pais = 'ag'; break;  //Argentina
				case "at": $pais = 'au'; break;  //AUSTRIA
				case "au": $pais = 'at'; break;  //AUSTRALIA
				case "be": $pais = 'be'; break;  //BÉLGICA
				case "bo": $pais = 'bo'; break;  //BOLIVIA
				case "br": $pais = 'bl'; break;  //BRASIL
				case "ca": $pais = 'cn'; break;  //CANADA - descontinuado
				case "co": $pais = 'ck'; break;  //COLOMBIA
				case "cr": $pais = 'cr'; break;  //COSTA RICA
				case "cs": $pais = 'cs'; break;  //CHECOSLOVAQUIA - descontinuado
				case "cu": $pais = 'cu'; break;  //CUBA
				case "ch": $pais = 'sz'; break;  //SUIZA
				case "cl": $pais = 'cl'; break;  //chile
				case "cn": $pais = 'cc'; break;  //CHINA
				case "de": $pais = 'gw'; break;  //ALEMANIA
				case "es": $pais = 'sp'; break;  //ESPAÑA
				case "fi": $pais = 'fi'; break;  //FINLANDIA
				case "fr": $pais = 'fr'; break;  //FRANCIA
				case "gb": $pais = 'xxk'; break;  //REINO UNIDO
				case "hk": $pais = 'hk'; break;  //HONG KONG - descont
				case "hu": $pais = 'hu'; break;  //HUNGRÍA
				case "ie": $pais = 'ie'; break;  //IRLANDA
				case "it": $pais = 'it'; break;  //ITALIA
				case "il": $pais = 'is'; break;  //ISRAEL
				case "in": $pais = 'ii'; break;  //INDIA
				case "jp": $pais = 'ja'; break;  //JAPÓN
				case "mx": $pais = 'mx'; break;  //MÉXICO
				case "no": $pais = 'no'; break;  //NORUEGA
				case "nz": $pais = 'nz'; break;  //NUEVA ZELANDIA
				case "nl": $pais = 'ne'; break;  //HOLANDA
				case "pa": $pais = 'pn'; break;  //PANAMÁ
				case "pl": $pais = 'pl'; break;  //POLONIA
				case "pt": $pais = 'po'; break;  //PORTUGAL
				case "py": $pais = 'py'; break;  //PARAGUAY
				case "pe": $pais = 'pe'; break;  //PERÚ
				case "ro": $pais = 'rm'; break;  //RUMANIA
				case "se": $pais = 'sw'; break;  //SUECIA
				case "sg": $pais = 'si'; break;  //SINGAPUR
				case "su": $pais = 'ru'; break;  //URSS - RUSIA
				case "tw": $pais = 'cc'; break;  //TAIWAN - china
				case "us": $pais = 'xxu'; break;  //EEUU
				case "eu": $pais = 'xxu'; break;  //EEUU
				case "usa": $pais= 'xxu'; break;  //EEUU
				case "uy": $pais = 'uy'; break;  //URUGUAY
				case "ve": $pais = 've'; break;  //VENEZUELA
				default: $pais = 'BIBUN:'.$cod_iso;
					$pais = "iso: ".$cod_iso;
	}
	/*
				case "hl": $pais = '--'; break;  //sin lugar
				case "ho": $pais = '--'; break;  //sin lugar
				case "vz": $pais = '--'; break;  //sin lugar
				case "pq": $pais = '--'; break;  //sin lugar
				case "rs": $pais = '--'; break;  //sin lugar
				case "ru": $pais = '--'; break;  //sin lugar
				case "sp": $pais = '--'; break;  //sin lugar
				case "wa": $pais = '--'; break;  //sin lugar
	*/
	return $pais;
}
?>