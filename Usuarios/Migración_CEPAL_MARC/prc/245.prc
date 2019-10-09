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
 *  
 *  Mapeo de campo 245
 *  ---------------------------------------------------------------------------------------------
 */

'<245>',
    /* primer indicador */
    '0',  /* título como punto de acceso principal */
        
    /* segundo indicador: caracteres a ignorar */
    if `¬L'¬L´¬[¬` : s('¬',v18.2,'¬') then 
      '2',
    else if '¬La ¬El ¬Le ¬An ¬' : s('¬',v18.3,'¬') then 
      '3',
    else if '¬Las ¬Los ¬The ¬Les ¬... ¬' : s('¬',v18.4,'¬') then 
      '4',
    else if '¿' = v18.1 then
      '1',
    else
      '0',
    fi,fi,fi,fi,
        
    /* subcampos */
    '^a',v18,
        
		/* mención de responsabilidad */
		,@prc/245c.prc,
		
    /* if not right(v18,1) = '.' then '.' fi, */
'</245>',