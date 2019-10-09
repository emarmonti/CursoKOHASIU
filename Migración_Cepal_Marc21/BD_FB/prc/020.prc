/* ---------------------------------------
 * Campo MARC 020 - ISBN
 *
 * Campo CEPAL 47 - ISBN
 * Sólo estamos considerando ISBN-10. Hay ISBN-13 en la base ??? <-- PENDIENTE (al 2007-08-25 no hay)
 * ---------------------------------------
 */

if p(v47) then
    /* Utilizamos el campo auxiliar (9010) */
    proc(
        'd9010',
        (
            '<9010>',v47,'<9010>',
        )
    ),
    
    /* Y ahora recorremos todas las ocurrencias de este campo auxiliar */
    (
        /* Creamos un campo auxiliar 9000 con el ISBN normalizado (le quitamos espacios
        y guiones, lo convertimos a mayúsculas ['x' => 'X'] y lo truncamos en 10 caracteres) */
        proc('d9000a9000~',
            left(
                replace(replace(s(mpu,v9010,mpl),
                    ' ',''),
                    '-',''),
                10             /* trunca en 10 caracteres */
            ),
        '~'),
        
        /* 9001: campo auxiliar para validar el número */
        /* Ver http://en.wikipedia.org/wiki/International_Standard_Book_Number#Check_digit_in_ISBN-10 */
        proc('d9001a9001~',
            f(
                (
                val(mid(v9000[1],1,1)) * 10 +
                val(mid(v9000[1],2,1)) * 9  +
                val(mid(v9000[1],3,1)) * 8  + 
                val(mid(v9000[1],4,1)) * 7  +
                val(mid(v9000[1],5,1)) * 6  +
                val(mid(v9000[1],6,1)) * 5  +
                val(mid(v9000[1],7,1)) * 4  +
                val(mid(v9000[1],8,1)) * 3  +
                val(mid(v9000[1],9,1)) * 2  +
                val(replace(mid(v9000[1],10,2),'X','10')) * 1
                ) 
                /11
                ,1,5
            ),
        '~'),
        /* '<9000>',v9000[1],'</9000>','<9001>',v9001[1],'</9001>' */
        
        /* Creamos un campo MARC 020, y decidimos el subcampo a usar según si el número es válido o no */
        '=020 ',
            '##',
            if right(v9001[1],5) = '00000' then
                /* resto cero => ISBN válido */
                '^a',
            else
                /* ISBN inválido */
                '^z',
            fi,
            replace(v9010,'-',''), /* conservamos el valor original del campo, previa eliminación de guiones */
    /),
fi,