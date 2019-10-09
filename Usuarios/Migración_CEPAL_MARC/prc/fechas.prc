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
 *  Mapeo de fechas
 *  ---------------------------------------------------------------------------------------------
 */

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
            /* Conviene evaluar fechas por medio de campos temporales para no perder datos originales */
            's',      /* tipo de fecha: 's' para fecha �nica */
            replace(replace(replace(v43^a,'c',''),'[',''),']','')	/* fecha 1 */
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
