**MigraBibun**
------------

La función de este paquete de scripts es realizar la converción de registros de una Base de Datos con BIBUN y exportarlo en un archivo mrcxml para poder importarlo en el sistema de gestión bibliotecario Koha.

Estos scripts son desarrollado en php y puede ser ejecutado en diferentes plataformas. Fue probado en ***Linux*** con PHP 7.2.

### Requerimientos de **MigraBibun**

- Utilitarios [**CISIS**](http://wiki.bireme.org/es/index.php/CISIS).
  Versión recomendada [Linux ver.
  5.7e](https://github.com/bireme/cisis/releases/download/64bits-5.7e-1030/cisis-64bits-5.7e-1030.tar.gz).

    > Se recomienda crear en /opt la carpeta cisis (/opt/cisis) y allí copiar los utilitarios descargados y otorgarles permisos de ejecución.
    Si ud. desea alojar estos utiliarios en otro directorio, debe modificar la variable PATH_CISIS='/opt/cisis' que se encuentra en cat2koha.sh. 
 
- Algunos conocimientos de [*Lenguaje de formteo de CISIS*](<http://modelo.bvsalud.org/download/cisis/CISIS-LinguagemFormato4-es.pdf>).
- PHP
- Se recomienda la aplicación [**MarcEdit**](<https://marcedit.reeset.net/>) para validar el archivo resultante para verificar que la estructura Marc sea válida y no haya errores en la codificación, por ej. indicadores inválidos.

### **Funcionamiento**

> El script trabaja con archivos de texto de la forma

```
 !ID mmmmmm
 !vXXX!...contents of tag XXX.............
 !vYYY!...contents of tag YYY.............
```
> Los que se pueden obtener con el aplicativo *i2id* de *cisis*
>
>  ***Sintaxis i2id***
>
>  *i2id <DB_origen> from=1 > <archivo de salida>*
>
> Además es conveniente que los campos de vinculación de registros madre-hijo se modifiquen a un campo compuesto. Es decir, el campo 079 debe estar compuesto por: 
>  *^z[nro acceso]^a[nro inventario]^p[precio material]^d[disponibilidad del material]^v[volumen ejemplar]^m[modo de adquisición]^l[biblioteca o localizacion]^e[estantería o temática]*
>
>  El script borrahijos.php permite hacer la vinculación de madres a hijos y de hijos a madres, dando como resultado 2 archivos, los registros madres con los hijos vinculados y los registros hijos que no pudieron ser vinculados.
>
>  ***Sintaxis borrahijos.php***
>
>  *php borrahijos.php <entrada.txt> <madres.txt> <hijos.txt>*
>
>  Parámetros:
>
>  <entrada.txt> archivo de texto obtenido con i2id
>
>  <madres.txt> archivo de texto con registros madres y los registros hijos unificados en el campo 079
>
>  <hijos.txt> archivo de texto con hijos que no pudieron vincularse a un registro madre
>
>  ***Sintaxis migra.php:***
>
>  *php migra.php <bibun.txt> <marcXML.xml> <config.ini>*
>
>  Parámetros:
>
>  <bibun.txt> archivo de entrada con registros a convertir. Se recomienda usar el archivo de salida *madres.txt* del script borrahijos.php.
>
>  <marcXML.xml> archivo de salida en formato marcXML, puede usarse directo en koha o manipularse con marcEdit
>
>  <config.ini> archivo de configuración para parametrizar la migración.
>
## ***Archivos de configuración***
>  
>  *FACET.ini*
>  Contiene reglas generales como localización de archivos y códigos, y reglas de control, está en desarrollo incorporar reglas de migración
> 
>  *replaceFACET.php*
>  El archivo replace contiene cadenas de texto a ser reemplazadas en el proceso de migración, permite corregir errores sobre la base y códigos incompletos o incorrectos, importa el orden, por lo que se debe tener en cuenta que el reemplazo de una cadena estará expuesto al analisis del siguiente reemplazo.
>
>
## Prueba el script
php borrahijos.php base/bibun.txt base/bibun_madres.txt base/bibun_hijos.txt > logs/log_borrarhijos.txt 
php migra.php base/bibun_madres.txt *facet.xml* config/FACET.ini > logs/log_migra.txt

## ***PARA TENER EN CUENTA***
Cada migración es diferente, estos scripts permiten hacer una limpieza y migración de registros, pero la habilidad del encargado de la migración es lo que hace la diferencia
