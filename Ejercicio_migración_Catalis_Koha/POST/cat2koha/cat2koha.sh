#!/bin/sh

#  ---------------------------------------------------------------------------------------------
#  Claudio M. Fuhr
#  claudiofuhr@gmail.com
#  Biblioteca Leo Falicov
#  Centro Atómico Bariloche - Instituto Balseiro
#
#  Marzo 2016
#
#  GNU General Public License v3.0
#
#  Este script elimina campos locales de Catalis no necesarios en KOHA.
#  Se incorpora el campo 942 donde se especifica el tipo de item:
#     BK (Libro) | CR (Periodical) | CF (CD-Rom) | VM (DVD, VHS) | MU (Sound) | MP (Map) | MX (Kit)
#  Se migran los datos del campo 859 de existencias en Catalis al campo 952 de KOHA.
#  Se utiliza el campo 999 temporalmente para construir los datos del LDR, que en Catalis están
#  distribuidos en los campos 905, 906, 907, 908, 909, 917, 918 y 919.
#  ---------------------------------------------------------------------------------------------

unset ${DB_SOURCE};
unset ${FROM_};
unset ${TO_};
unset ${DB_NAME};
unset ${SOURCE_FOLDER};
unset ${WORK_FOLDER};
unset ${TARGET_FOLDER};

# Especificar el path de los utilitarios de CISIS
# Verifique que tiene permisos de ejecución
PATH_CISIS='/opt/cisis';
PATH=$PATH:$PATH_CISIS;
export PATH;

exec_name=`basename $0 .sh`

# -----------------------------------------
# FUNCIONES
# -----------------------------------------

HELP() {

  echo
  echo "  Ayuda para utilizar el comando: ${exec_name}.sh"
  echo
  echo "  Parámetros necesarios para migrar registros de Catalis a un archivo .mrc para importar en Koha."
  echo
  echo "  Sintaxis:"
  echo "    ${exec_name}.sh --origen <DB_origen> --from <Nro. MFN inicial> --to <Nro. MFN final>"
  echo
  echo "      -b | --db     <Nombre DB de origen (respetar mayúsculas y minúsculas)."
  echo "                Si la misma se encuentra dentro de una carpeta ingresar './nombre_carpeta/nombre_bd'"
  echo "                o la ruta absoluta a la base de datos.>"
  echo "      -f | --from   <Opcional. Número de MFN inicial entero mayor que 0.>"
  echo "      -t | --to     <Opcional. Número de MFN final mayor o igual que el valor utilizado en el parámetro --from.>"
  echo
  exit 0
}


DB_EXIST() {

# Verificamos que la BD pasada como parámetro existe.
if [ "${1}" = "" ]; then
    echo "Debe ingresar nombre de Base de datos existente."
    exit 1
else
    # Verificamos si un directorio, pasado como parámetro, existe 
    if [ -d "${1}" ]; then
      echo La carpeta ${1} existe.
    else
      echo El carpeta ${1} no existe. Verifique los parámetros ingresados.
      exit 1
    fi
fi

}

# ----------------------------------------------


#-------------------
# Manual de ayuda
#-------------------
if [ -z ${1} ] || [ "${1}" = "--help" ]; then
  HELP;
fi


#---------------------------------------------
# Verificar valores pasados por parámetros:
#---------------------------------------------

# param_control contendrá el nombre de la variable en la que
# debe almacenarse el siguiente parámetro

param_control="null"

# recorrido del arreglo de parámetros

for param in "$@"; do

	case ${param} in
	"-b")
		param_control="DB_SOURCE"
		;;
    "--db")
		param_control="DB_SOURCE"
		;;
    "-f")
		param_control="FROM_"
		;;
	"--from")
		param_control="FROM_"
		;;
    "-t")
        param_control="TO_"
        ;;
    "--to")
        param_control="TO_"
        ;;
	*)
	case ${param_control} in
	"DB_SOURCE")
		DB_SOURCE=${param}
		;;
	"FROM_")
		FROM_=${param}
		;;
	"TO_")
		TO_=${param}
		;;
	esac

	param_control="null"

	esac
done

if [ "${DB_SOURCE}" = "" ]; then
  echo
  echo "BD origen no especificada. Saliendo."
  HELP
  exit 1
fi

# Cantidad de registros de la BD a procesar
NEXT_MFN_DB_IN=`mx ${DB_SOURCE} +control count=-1 | tail -n 1 | tr -s '   ' | tr ' ' '\n' | head -n 2 | tail -n 1`;
LAST_MFN_DB_IN=`echo ${NEXT_MFN_DB_IN} - 1 | bc -l`;

if [ "${FROM_}" = "" ]; then
  FROM_='1';
elif [ "${FROM_}" -lt "0" ]; then
  # FROM_ menor que 0.
  echo "El valor ingrasado para FROM_ debe ser mayor que 0."
  HELP;
elif  [ "${FROM_}" -gt "0" ]; then
  # FROM_ mayor que 0.
  FROM_MX='from='${FROM_};
fi

if [ "${TO_}" = "" ]; then
  TO_=${LAST_MFN_DB_IN};
elif [ "${TO_}" -lt "0" ]; then
  # TO_ menor que 0.
  echo "El valor ingrasado para TO_ debe ser mayor que 0."
  HELP;
elif [ "${TO_}" -lt "${FROM_}" ]; then
  # TO_ debe ser mayor o igual que FROM_.
  echo "El valor del parámetro TO_ debe ser mayor o igual que el valor del parámetro FROM_."
  HELP;
elif [ "${TO_}" -gt "0" ]; then
  # TO_ mayor que 0.
  TO_MX='to='${TO_};
fi

## Punto de control de parámetros ingresados
## Descomentar para probar
#echo "Ingresó estos parámetros: "
#echo "BD de origen: "${DB_SOURCE}
#if [ "${FROM_}" = "" ]; then
#  echo "desde el mfn 1"
#else
#  echo "desde "${FROM_};
#fi
#if [ "${TO_}" = "" ]; then
#  echo "hasta el final"
#else
#  echo "hasta "${TO_};
#fi
#
#exit 0

##
DATE=`date +'%Y-%m-%d_%H-%M'`;
DB_NAME=`echo ${DB_SOURCE} | tail -n 1 | tr '/ ' '\n' | tail -n 1`
SOURCE_FOLDER='import';
WORK_FOLDER='tmp';
TARGET_FOLDER='export';
##

clear

echo
echo "Conversión de BD de Catalis para importar en Koha"
echo "-------------------------------------------------"
echo

# Verificamos si existen los folders que utilizaremos
# en el proceso de conversión.
if [ ! -d "${SOURCE_FOLDER}" ]; then
  echo "No existe ${SOURCE_FOLDER}. Creando..."
  mkdir ${SOURCE_FOLDER}
fi
if [ ! -d "${WORK_FOLDER}" ]; then
  echo "No existe ${WORK_FOLDER}. Creando..."
  mkdir ${WORK_FOLDER}
fi
if [ ! -d "${TARGET_FOLDER}" ]; then
  echo "No existe ${TARGET_FOLDER}. Creando..."
  mkdir ${TARGET_FOLDER}
fi

echo "Realizamos una copia de la BD a procesar en la carpeta ./import"
mx ${DB_SOURCE} create=${SOURCE_FOLDER}/${DB_NAME} now -all tell=200

echo "Conversión de campo 859 a 952"
mx ${DB_SOURCE} ${FROM_MX} ${TO_MX} proc=@prc/cat2koha.prc create=${WORK_FOLDER}/${DB_NAME}_tmp now -all tell=200

echo "Conversión de ISIS a ISO para eliminar registros borrados lógicamente"
# Convertimos la BD a formato ISO para quitar registros con borrado lógico
mx ${WORK_FOLDER}/${DB_NAME}_tmp iso=${WORK_FOLDER}/${DB_NAME}_tmp.iso now -all tell=200
echo "Conversión de ISO a ISIS"
mx iso=${WORK_FOLDER}/${DB_NAME}_tmp.iso create=${WORK_FOLDER}/${DB_NAME}_2koha now -all tell=200

# Copiamos base isis a directorio de exportación
#mx ${WORK_FOLDER}/${DB_NAME}_tmp_iso create=${WORK_FOLDER}/${DB_NAME}_2koha now -all tell=200

echo "Conversión a formato MRK"
# Relizamos el proceso de conversión a formato MRK
i2id ${WORK_FOLDER}/${DB_NAME}_2koha > ${WORK_FOLDER}/${DB_NAME}_2koha.id
python cat2mkr/cat2mrk.py ${WORK_FOLDER}/${DB_NAME}_2koha

# Renombramos el campo 999 por 000 para que la conversión a mrc
# pase a ser LDR
#
# He notado que si los registros del archivo de entrada no tienen LDR
# MarcMaker.py establece un LDR por default a todos los registros.
#
# default_leader = '00000nam a2200000uu 4500'
#
# Es necesario que se calcule la longitud del registro cuando pase a mrc.
# 
# Ej.: LDR = '01354nam a2200000uu 4500'
# 
sed -i 's/=999/=LDR/g' $WORK_FOLDER/${DB_NAME}_2koha.mrk

echo "Conversión a formato MRC"
# Realizamos el proceso de conversión a formato MRC
python mrk2mrc/MarcMaker.py < ${WORK_FOLDER}/${DB_NAME}_2koha.mrk > ${TARGET_FOLDER}/${DB_NAME}_${DATE}_${FROM_}-${TO_}.mrc

# Cantidad de registros de la BD a procesados
NEXT_MFN_DB_OUT=`mx ${WORK_FOLDER}/${DB_NAME}_2koha +control count=-1 | tail -n 1 | tr -s '   ' | tr ' ' '\n' | head -n 2 | tail -n 1`;
LAST_MFN_DB_OUT=`echo ${NEXT_MFN_DB_OUT} - 1 | bc -l`;

echo
echo "Total de registros procesados: ${LAST_MFN_DB_OUT}"
echo
echo "Resultado de la conversión ./${TARGET_FOLDER}/${DB_NAME}_${DATE}_${FROM_}-${TO_}.mrc"
echo
echo "Aclaración: Si existe alguna diferencia entre la cantidad de registros procesados"
echo "y el nro. de mfn final es porque existieron registros borrados logicamente"
echo "y en la conversión a ISO desaparecieron."
echo
echo "Proceso finalizado."
echo

exit 0


