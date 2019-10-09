#!/bin/sh

#  ---------------------------------------------------------------------------------------------
#  Claudio M. Fuhr
#  claudiofuhr@gmail.com
#  Biblioteca Leo Falicov
#  Centro Atómico Bariloche - Instituto Balseiro
#
#  Julio 2010
#  Revisión Septiembre 2019
#
#  GNU General Public License v3.0
#
#  Migración de una BD Isis con formato CEPAL de la Fundación Bariloche.
#  ---------------------------------------------------------------------------------------------

# Copiamos la BD original
echo "Copiamos la BD original"
mx original/fb create=fb now -all tell=25

# Aplicamos gizmo l_g_split (cepal->marc21)
echo "Aplicamos gizmo l_g_split_brakets"
mx fb "gizmo=gizmos/l_g_split_brakets,18,76,77,78,80,81" copy=fb now -all tell=25

# Aplicamos split para separar en ocurrencias campos repetibles
echo "Aplicamos split para separar en ocurrencias campos repetibles"
mx fb "proc='Gsplit/clean=76=|'" "proc='Gsplit/clean=77=|'" "proc='Gsplit/clean=78=|'" "proc='Gsplit/clean=80=|'" "proc='Gsplit/clean=81=|'" copy=fb now -all tell=25

# Aplicamos gizmo l_g_descriptores (cepal->marc21)
echo "Aplicamos gizmo l_g_descriptores"
mx fb "proc='Ggizmos/l_g_descriptores,76,77,78,81'" copy=fb now -all tell=25

# Aplicamos gizmo l_g_idioma (cepal->marc21)
echo "Aplicamos gizmo l_g_idioma"
mx fb "proc='Ggizmos/l_g_idioma,64'" copy=fb now -all tell=25

# Aplicamos gizmo l_g_pais (cepal->marc21)
echo "Aplicamos gizmo l_g_pais"
mx fb "proc='Ggizmos/l_g_country-codes,83,84'" copy=fb now -all tell=25

# Aplicamos gizmo l_g_autorpers
echo "Aplicamos gizmo l_g_autorpers"
mx fb "proc='Ggizmos/l_g_autorpers,16'" copy=fb now -all tell=25
mx fb "gizmo=gizmos/l_g_split_brakets,16" copy=fb now -all tell=25
mx fb "proc='Gsplit/clean=16=|'" copy=fb now -all tell=25

# Aplicamos gizmo l_g_autorinst
echo "Aplicamos gizmo l_g_autorinst"
mx fb "proc='Ggizmos/l_g_autorinst,17'" copy=fb now -all tell=25
mx fb "gizmo=gizmos/l_g_split_brakets,17" copy=fb now -all tell=25
mx fb "proc='Gsplit/clean=17=|'" copy=fb now -all tell=25

# Aplicamos gizmo g_fechas
echo "Aplicamos gizmo g_fechas"
mx fb "proc='Ggizmos/l_g_fechas,43'" copy=fb now -all tell=25
mx fb "proc=if a(v43^a) and v43<>'' then 'd43','<43>^a',v43,'</43>',fi" copy=fb now -all tell=25

# Aplicamos gizmo g_dfisicos (cepal->AACR2)
echo "Aplicamos gizmo g_dfisicos"
mx fb "proc='Ggizmos/l_g_dfisicos,42'" copy=fb now -all tell=25

# Aplicamos gizmo g_paginas (cepal->AACR2)
echo "Aplicamos gizmo g_paginas"
mx fb "proc='Ggizmos/l_g_paginas,20'" copy=fb now -all tell=25

# Aplicamos script de migración a Marc21
echo "Aplicamos script a Marc21"
mx fb "proc=@prc/cepal2marc.prc" copy=fb now -all tell=25

# Relizamos el proceso de conversión a formato MRK
echo "Conversión de Isis a Id"
i2id fb > fb-CP437.id
echo "Conversión de caracteres de CP437 a ISO-8859-1"
iconv -f CP437 -t ISO-8859-1//TRANSLIT fb-CP437.id -o fb.id

echo "Conversión a formato MRK"
python cat2mkr/cat2mrk.py fb

# Renombramos el campo 999 por LDR
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
sed -i 's/=999/=LDR/g' fb.mrk

# Realizamos el proceso de conversión a formato MRC
echo "Conversión a formato MRC"
python mrk2mrc/MarcMaker.py < fb.mrk > fb.mrc

