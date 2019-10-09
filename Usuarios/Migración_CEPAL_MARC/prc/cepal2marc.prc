/*
 *  ---------------------------------------------------------------------------------------------
 *  Claudio M. Fuhr
 *  claudiofuhr@gmail.com
 *  Biblioteca Leo Falicov
 *  Centro Atómico Bariloche - Instituto Balseiro
 *
 *  Julio 2010
 *  Revisión Septiembre 2019
 *
 *  Script que mapea el contenido de campos Isis en formato CEPAL
 *  a formato Marc21
 *  ---------------------------------------------------------------------------------------------
 */

/* ------------------------------------------
 * Antes de migrar borramos el contenido de
 * cada campo.
 * ------------------------------------------
 */
'd*',

/* ------------------------------------------
 * Campo MARC LDR
 *
 * Sabemos que los registros de la BD son del tipo monográficos
 * Creamos el campo LDR con datos fijos.
 * ------------------------------------------
 */
'<999>00000nam a2200000uu 4500</999>',/,

/* ------------------------------------------
 * Campo MARC 001 - Número de control
 *
 * Almacenamos el MFN que tiene el registro en la base unificada (no tiene relación
 * con los MFN de la base original).
 * ------------------------------------------
 */
'<001>',mfn(6),'</001>',/,

/* ------------------------------------------
 * Campo MARC 003 - Código MARC de la biblioteca
 *
 * El no hay código asignado por LC.
 * Ver http://www.loc.gov/marc/organizations/org-search.php
 * ------------------------------------------
 */

'<003>ARFB</003>',/,

/* ---------------------------------------
 * Campo MARC 005 - Fecha y hora de la última modificación
 *
 * Usamos la fecha y hora de la migración.
 * ---------------------------------------
 */

'<005>',s(date).8, s(date)*9.6,'.0','</005>',/,

/* -----------------------------------
 * Campo MARC 008 - Datos codificados
 * -----------------------------------
 */
 /* Utilizamos un archivo externo */
@prc/008.prc,/,

/* ---------------------------------------
 * Campo MARC 020 - ISBN
 *
 * Campo CEPAL 47 - ISBN
 * No existe campo entre los registros existentes
 * ---------------------------------------
 */

/* ----------------------------
 * Campo MARC 041 - Códigos de idioma
 *
 * Campo CEPAL 64 - Idioma del documento
 * Creamos un campo MARC 041 sólo si hay más de una ocurrencia del campo CEPAL 64.
 * NOTA: Los códigos deben haber sido convertidos previamente de ISO 639-1 (2 letras)
 * a ISO 639-2 (3 letras).
 * ----------------------------
 */

if v64<>'' then
    '<041>',
        '##',
        (
          '^a',v64,
        )
    '</041>',
fi,

/* ----------------------------------
 * Campo MARC 044 - Código de país de publicación/producción
 *
 * Campo CEPAL 83 - Código de país primario
 * Campo CEPAL 84 - Código de país secundario
 * Creamos un campo MARC 044 sólo si hay más de una ocurrencia del campo CEPAL 83 o CEPAL 84.
 * ----------------------------------
 */
if v83<>'' then
  '<044>',
    '##',
    (
      '^c',replace(v83,'#',''),
    ),
    if v84<>'' then
      (
        '^c',replace(v84,'#',''),
      ),
    fi,
  '</044>',
else if a(v83) and v84<>'' then
  '<044>',
    '##',
    (
      '^c',replace(v84,'#',''),
    )
  '</044>',
fi,fi,

/* ---------------------------------
 * Campo MARC 245 - Título y mención de responsabilidad
 *
 * Campo FOCAD 18 - Título monográfico
 *
 * Mención de responsabilidad: la decisión es no construir un 245 $c,
 * arreglo pendiente por parte de la bibliotecaria.
 * ---------------------------------
 */
if v18<>'' then
  @prc/245.prc,/,
fi,/,

/* --------------------------
 * Campo MARC 250 - Mención de edición
 *
 * Campo CEPAL 41 - Edición
 * --------------------------
 */
if p(v41) then
    '<250>',
        '##',
        '^a',replace(v41,'da.','a.'),
        if right(v41,1) <> '.' then '.', fi, /* puntuación final */
    '</250>',
fi,

/* --------------------------
 * Campo MARC 260 - Datos de publicación: lugar, editor, fecha
 *
 * Campo CEPAL 39 - Lugar de edición
 * Campo CEPAL 38 - Editor
 * Campo CEPAL 45 - Fecha de publicación
 * 
 * ------------------------
 */

if p(v39) or p(v38) then
    '<260>',
        '##',
        
        /* Editor y lugar */
        if p(v39) then
            if v39:'s.l' and v38:'s.e' then
                '^a[S.l. :^bs.n.]',
            else
                (
                if v39<>'s.l' then
                  '^a',v39,
                else 
                    '^a',replace(v39,'s.l.','[S.l.]'),
                fi,
                if v38<>'s.e' then
                  ' :^b',replace(replace(v38,'<',''),'>',''),
                else
                  ' :^b', replace(v38,'s.e','[s.n.]'),
                fi,
                if iocc < nocc(v38) then
                    ';',
                fi,
                )
            fi,
        fi,
        
        /* Fecha */
        if p(v43) then
            
            if v38<>'' then ',', fi, /* el subcampo c va precedido de una coma */
            '^c',

            /* CASO 1: el campo 45 posee una fecha completa (4 dígitos), y posiblemente un rango de fechas */

            if val(v43^a*0.4) > 1500 then,
                /* casos considerados:
                    a) 1956
                    b) 1956-
                    c) 1956-7
                    d) 1956-57
                    e) 1899-900
                    f) 1956-1957
                */
                v43^a,

            /* CASO 2: el campo contiene una fecha estimada */
                
            else if v43^a*3.1 = '-' then,
                /* casos considerados:
                    a) 195-
                    b) 19--
                */
                '[',v43^a,']',
            
            else if v43^a:'[' then
              v43^a,

            /* CASO 3: el campo no contiene una fecha (esté o no presente el campo 43) */

            else if val(v43^a*0.4) = 0 then,
                /* casos considerados:
                    a) el campo 43 está presente, pero no contiene ningún dígito > 0
                    b) el campo 43 está ausente
                */
                '[s.f.]',
            fi,fi,fi,fi,
        fi,
        '.',   /* puntuación final */
    '</260>',
fi,

/* ------------------------------------
 * Campo MARC 300 - Descripción física
 *
 * Campo CEPAL 20 - Número de páginas
 * Campo CEPAL 42 - Información descriptiva (ATENCION: en este subcampo se han usado términos no contemplados en las AACR2:'fotos', 'gráfs.', 'dibs.', etc)
 * Campo CEPAL ¿? - 300^c Dimensiones
 * ----------------------------------
 */

if p(v20) or p(v42) then
    '<300>',
        '##',
        if v20<>'' then
            '^a',replace(replace(v20,'<',''),'>',''),
        fi,
        if p(v20) then
          if p(v42) then
            ' ;^b',replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(v42,'.^d',', '),'.^b',', '),'.^k',', '),'s^d',', '),'.^a',', '),'^a',''),'^d',''),'^b',''),'^k',''),'.',''),
            '.',
          fi,
        else
          '^b',replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(v42,'.^d',', '),'.^b',', '),'.^k',', '),'s^d',', '),'.^a',', '),'^a',''),'^d',''),'^b',''),'^k',''),'.',''),
          '.',
        fi,
    '</300>',
fi,

/* ----------------------
 * Campo MARC 490 - Mención de serie
 *
 * Campo CEPAL 25 - Título (nivel colección)
 * ----------------------
 */

/* ----------------------
 * Campo MARC 500 - Nota general
 *
 * Campo CEPAL 68 - Notas
 * ----------------------
 */
if v68<>'' then
  (
    '<500>',
      '##',
      '^a',v68
      if right(v68,1) <> '.' then '.', fi, /* puntuación final */
    '</500>',
    )
fi,

/* ----------------------
 * Campo MARC 504 - Nota de bibliografía
 *
 * Campo CEPAL 73 - Número de referencias bibliográficas
 * Construimos una nota usual ('Incluye referencias bibliográficas.') y le insertamos el número
 * almacenado en el campo 49.
 * ----------------------
 */
if v73<>'' then
    '<504>',
        '##',
        '^a',replace(replace(replace(v73,'incl','Incl'),'<',''),'>',''),
        if right(v73,1) <> '.' then '.', fi, /* puntuación final */
    '</504>',
fi,

/* ----------------------------
 * Campo MARC 520 - Nota de resumen
 *
 * Campo CEPAL 72 - Resumen
 * ----------------------------
 */

if p(v72) then
  '<520>',
    '##',
    '^a',v72,
    if right(v72,1) <> '.' then '.', fi, /* puntuación final */
  '</520>',
fi,

/* ----------------------------
 * Campo MARC 100 - Punto de acceso principal--Nombre personal
 * Campo MARC 700 - Punto de acceso secundario--Nombre personal
 *
 * Campo CEPAL 16 - Autor personal (nivel monográfico) Repetible
 * Campo CEPAL 17 - Autor institucional (nivel monográfico) Repetible
 * ----------------------------
 */
if p(v16) then
(
  if nocc(v16)>1 then 
    if iocc=1 then
      '<100>1#^a',
        v16^a,', ',v16^b,
        if right(v16,1) <> '.' then '.', fi, /* puntuación final */
      '</100>',
    else
      '<700>1#^a',
        v16^a,', ',v16^b,
        if right(v16,1) <> '.' then '.', fi, /* puntuación final */
      '</700>',
    fi,
  else
    '<100>1#^a',
      v16^a,', ',v16^b,
      if right(v16,1) <> '.' then '.', fi, /* puntuación final */
    '</100>',
  fi,
/),
fi,

/* ----------------------------
 * Campo MARC 110 - Punto de acceso principal--Nombre institucional
 * Campo MARC 710 - Punto de acceso secundario--Nombre institucional
 *
 * Campo CEPAL 17 - Autor institucional (nivel monográfico) Repetible
 * ----------------------------
 */
if p(v17) then
(
  if nocc(v17)>1 then 
    if iocc=1 then
      '<110>1#^a',
        v17^i,
        if right(v17,1) <> '.' then '.', fi, /* puntuación final */
      '</110>',
    else
      '<710>1#^a',
        v17^i,
        if right(v17,1) <> '.' then '.', fi, /* puntuación final */
      '</710>',
    fi,
  else
    '<110>1#^a',
      v17^i,
      if right(v17,1) <> '.' then '.', fi, /* puntuación final */
    '</110>',
  fi,
/),
fi,

/* ------------------------
 * Campo MARC 653 - Descriptores (no controlados)
 *
 * Campo CEPAL 76 - Descriptores
 * ------------------------
 */
if p(v76) then
  (
    '<653>##^a',v76,'</653>',
  ),
fi,

/* ---------------------------
 * Campo KOHA 942 - Items
 * ---------------------------
 */
'<942>##^cBK</942>',

/* ---------------------------
 * Campo KOHA 952 - Items
 * ---------------------------
 */
(
  '<952>',
    '##',
    '^aARBIB',
    '^bARBIB',
    '^i','FB',f(val(v8),3,0),
    '^p','FB',f(val(v8),3,0),
    '^yBK',
    '^x',v3,
  '</952>',
),