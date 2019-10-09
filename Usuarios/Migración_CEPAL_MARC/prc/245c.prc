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
 *  Mapeo de campo 245 subcamo c
 *  ---------------------------------------------------------------------------------------------
 */


/*	Mención de responsabilidad
 *
 *	CEPAL 10 : Autor personal - Nivel analítico
 *	CEPAL 11 : Autor institucional - Nivel analítico
 *
 *	----------
 *	Se decide no incorporar el autor institucional en el v245^c
 *	hasta que en posterior corrección se normalice
 *	----------
 *
 *	Utilizamos campo auxiliar:
 *								9800
 *
 */

proc
	(
		'd9800',
		if p(v16) and v16^b<>'' then
			('a9800#',v16^b,' ',v16^a,'#'/),
		else
			('a9800#',v16^a,'#'/),
		fi,
	)

if v9800 <> '' then
	' /^c',
	if nocc(v9800)<2 then
		v9800,
		if not right(v9800,1) = '.' then
			'.'
		fi,
	else
		if nocc(v9800)<4 then
			(|, |+v9800)
			select nocc(v9800)
				case 2 : if not right(v9800[2],1) = '.' then '.' fi,
				case 3 : if not right(v9800[3],1) = '.' then '.' fi,
			endsel,
		else
			v9800[1],' ... [et al.].'
		fi,
	fi,
fi,
