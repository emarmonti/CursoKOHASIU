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
 *  Mapeo de campo 008
 *  ---------------------------------------------------------------------------------------------
 */

/* ---------
 * Campo MARC 008 - Datos codificados
 *
 * Este campo se construye con datos procedentes de varios campos del registro original.
 * ATENCION: el significado de las posiciones 18-34 var�a seg�n se trate de libros o videos.
 * Para encarar estas diferencias, podr�amos separar completamente el tratamiento de cada caso,
 * o bien (como hacemos ac�) usar varios 'if-then-else-fi' a lo largo del camino cada vez que
 * surgen las diferencias.
 *
 * Campo auxiliar: 9900
 *
 * -----------
 */
'<008>'
    /* ------------------------------------------
     * 008/00-05 - Fecha de creaci�n del registro
     * Usamos la fecha de la migraci�n.
     * ------------------------------------------
     */
    mid(date,3,6),
    
    /* ------------------------------------------
     * 008/06    - Tipo de fechas
     * 008/07-10 - Fecha 1
     * 008/11-14 - Fecha 2
     * Nos basamos en el campo CEPAL 43 (Fecha de publicaci�n) y consideramos 3 casos.
	 *
	 * Casos:
	 *			1) fecha completa
	 *			2) fecha estimada
	 *			3) sin fecha
	 *
     * ------------------------------------------
     */
    
    /* CASO 1: el campo CEPAL 43 posee una fecha completa (4 d�gitos), y alg�n un rango de fechas */

    if val(v43^a) > 1900 then,
        /* casos considerados:
            a) 1971
            b) 1975-1990
			c) c1981
        */
        if v43^a*4.1 = '-' then  /* fechas m�ltiples */
            'm',      /* tipo de fechas: 'm' para m�ltiples */
            v43^a*0.4,  /* fecha 1 */
            select size(v43^a*5)        /* fecha 2 */
                case 0   : 'uuuu',  /* si supi�ramos que la publicaci�n contin�a, pondr�amos '9999' */
                case 1   : v43^a.3, v43^a*5,
                case 2   : v43^a.2, v43^a*5,
                case 3   : v43^a.1, v43^a*5,
                case 4   :        v43^a*5,
                elsecase '####'
            endsel,
        else   /* fecha simple, eliminamos 'c' de copyright */
            's',      /* tipo de fecha: 's' para fecha �nica */
            replace(replace(replace(replace(v43^a,'c',''),'[',''),']',''),'?','')	/* fecha 1 */
            '####',   /* fecha 2 */
        fi,
    
    /* CASO 2: el campo CEPAL 43 contiene una fecha estimada */
        
    else if val(v43^a) > 0 and val(v43^a) < 1000 then,
        /* casos considerados:
            a) [196-?]
			b) [1999?]
        */
        's',                   /* fecha �nica */
        replace(replace(replace(replace(v43^a,'-','u'),'[',''),']',''),'?','u'),  /* fecha 1 */
        '####',                /* fecha 2: no aplicable */

    /* CASO 3: el campo CEPAL 43 no contiene una fecha o est� ausente */

    else if val(v43^a) = 0 then,
        /* casos considerados:
            a) el campo 43 est� presente, pero no contiene ning�n d�gito > 0
            b) el campo 43 est� ausente
        */
        'n',       /* fecha desconocida */
        'uuuu',    /* fecha 1: desconocida */
        '####',    /* fecha 2: no aplicable */

    fi,fi,fi,
    
    /* ------------------------------------------
     * 008/15-17 - Lugar de publicaci�n
     * Campo CEPAL 83 - C�digo de pa�s (revisar nombre de campo ??)
     * Si no existe campo 83 utilizar campo 84
     * S�lo usamos la primera ocurrencia del campo 83 o primera ocurrencia del campo 84.
     * NOTA: el c�digo ISO fue previamente convertido al c�digo MARC mediante un gizmo.
     * ------------------------------------------
     */
    if p(v83) then
        if size(v83[1]) = 2 then
            v83[1],'#', 
        else
            v83[1].3,
        fi,
    else if p(v84) then
        if size(v84[1]) = 2 then
            v84[1],'#', 
        else
            v84[1].3,
        fi,
    else
        'xx#',  /* 'xx#' para lugar desconocido */
    fi,fi,

    /* Comienzo del bloque de datos dependientes del tipo de material (008/18-34) */
    
        /* ------------------------------------------
		* 008/18-21 - Ilustraciones
        * ------------------------------------------
        */
        proc('d9900a9900|'
			if p(v42^a) then 'a' else '#',fi,
			if p(v42^b) then 'b' else '#',fi,
			if p(v42^d) then 'd' else '#',fi,
			if p(v42^k) then 'k' else '#',fi,
			'|'
			),
		v9900,

        /* ------------------------------------------
        * 008/22 - Audiencia
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/23 - Forma del �tem
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/24-27 Naturaleza del contenido
        * ------------------------------------------
        */
        '####',

        /* ------------------------------------------
        * 008/28 - Publicaci�n gubernamental
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/29 - Publicaci�n de conferencia
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/30 - Festschrift
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/31 - Indice
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/32 - Indefinido
        * ------------------------------------------
        */
        '#',

        /* ------------------------------------------
        * 008/33 - Forma literaria
        * ------------------------------------------
        */
        '|',

        /* ------------------------------------------
        * 008/34 - Biograf�a
        * ------------------------------------------
        */            
        '#', /* '#': sin material biogr�fico */
        
    /* Fin del bloque de datos dependientes del tipo de material (008/18-34) */        
    
    /* ------------------------------------------
     * 008/35-37 - Idioma 
     * Campo CEPAL 64 - Idioma
     * NOTA: el valor del campo fue previamente convertido mediante un gizmo.
     * ------------------------------------------
     */
    if size(v64[1]) = 3 then
        v64[1],
    else
        'und',  /* 'und' para idioma no determinado */
    fi,
    
    /* ------------------------------------------
     * 008/38 - Registro modificado
     * ------------------------------------------
     */
    '|',
    
    /* ------------------------------------------
     * 008/39 - Fuente de la catalogaci�n
     * ------------------------------------------
     */
    '|',
'</008>',
