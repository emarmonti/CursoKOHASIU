/* ------------------------------------------
 * Campo MARC LDR
 *
 * Sabemos que los registros de la BD son del tipo monográficos
 * Creamos el campo LDR con datos fijos.
 * ------------------------------------------
 */
'=LDR 00000nam  2200000 a 4500'/

/* ------------------------------------------
 * Campo MARC 001 - Número de control
 *
 * Almacenamos el MFN que tiene el registro en la base unificada (no tiene relación
 * con los MFN de la base original).
 * ------------------------------------------
 */
'=001 ',mfn(6),/,

/* ------------------------------------------
 * Campo MARC 003 - Código MARC de la biblioteca
 *
 * El no hay código asignado por LC.
 * Ver http://www.loc.gov/marc/organizations/org-search.php
 * ------------------------------------------
 */

'=003 ARFB',/,

/* ---------------------------------------
 * Campo MARC 005 - Fecha y hora de la última modificación
 *
 * Usamos la fecha y hora de la migración.
 * ---------------------------------------
 */

'=005 ',s(date).8, s(date)*9.6,'.0',/,

/* -----------------------------------
 * Campo MARC 008 - Datos codificados
 * -----------------------------------
 */
 /* Utilizamos un archivo externo */
/*,@./008.prc,*/

/* ---------------------------------------
 * Campo MARC 020 - ISBN
 *
 * Campo CEPAL 47 - ISBN
 * Sólo estamos considerando ISBN-10. Hay ISBN-13 en la base ??? <-- PENDIENTE (al 2007-08-25 no hay)
 * ---------------------------------------
 */
 /* utilizamos un archivo externo */
/* ,@020.prc, */ 

/* (|=020 \\\\$a|,v047,/),/, */
|=245 \\\\$a|v18,/,
(if nocc(v16)>1 then 
  if iocc=1 then
    |=100 1#$a|,v16,
  else
    |=700 1#$a|,v16,
  fi,
else
  |=100 1#$a|,v16,/,
fi,'.',/),
|=110 \\\\$a|,v17^p,if p(v29^s) then |$b|v29^s,fi,/,
|=111 \\\\$a|,v40^n,/,
|=500 \\\\$a|,v68,/,
|=520 \\\\$a|,v72,/,
|=591 \\\\$a|,v59,/,
(|=650 \\\\$a|,v76/),/,
(|=653 \\\\$a|,v77/),/,
(if nocc(v16)>1 then 
  if iocc>1 then
    |=700 \\\\$a|,v16,
  fi,
fi,/),
'=942 \\\\$cBK',/,
(|=952 \\\\$p|v8|$aARBIB$bARBIB|/),
#