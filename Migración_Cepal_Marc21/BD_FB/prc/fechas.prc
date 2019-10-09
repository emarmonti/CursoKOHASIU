    if val(v43^a) > 1900 then,
        /* casos considerados:
            a) 1971
            b) 1975-1990
			c) c1981
        */
        if v43^a*4.1 = '-' then  /* fechas múltiples */
            'm',      /* tipo de fechas: 'm' para múltiples */
            v43^a*0.4,  /* fecha 1 */
            select size(v43^a*5)        /* fecha 2 */
                case 0   : 'uuuu',  /* si supiéramos que la publicación continúa, pondríamos '9999' */
                case 1   : v43^a.3, v43^a*5,
                case 2   : v43^a.2, v43^a*5,
                case 3   : v43^a.1, v43^a*5,
                case 4   :        v43^a*5,
                elsecase '####'
            endsel,
        else   /* fecha simple, eliminamos 'c' de copyright */
            's',      /* tipo de fecha: 's' para fecha única */
            replace(replace(replace(v43^a,'c',''),'[',''),']','')	/* fecha 1 */
            '####',   /* fecha 2 */
        fi,
    
    /* CASO 2: el campo CEPAL 43 contiene una fecha estimada */
        
    else if val(v43^a) > 0 and val(v43^a) < 1000 then,
        /* casos considerados:
            a) [196-?]
			b) [1999?]
        */
        's',                   /* fecha única */
        replace(replace(replace(replace(v43^a,'-','u'),'[',''),']',''),'?','u'),  /* fecha 1 */
        '####',                /* fecha 2: no aplicable */

    /* CASO 3: el campo CEPAL 43 no contiene una fecha o está ausente */

    else if val(v43^a) = 0 then,
        /* casos considerados:
            a) el campo 43 está presente, pero no contiene ningún dígito > 0
            b) el campo 43 está ausente
        */
        'n',       /* fecha desconocida */
        'uuuu',    /* fecha 1: desconocida */
        '####',    /* fecha 2: no aplicable */

    fi,fi,fi,
