# Cecabank Módulo Prestashop

# Más información

Para tener más información de como instalar el plugin de Cecabank en Prestashop puede ver el manual de este plugin en la sección Ayuda y Recursos de la consola de Cecabank.

# Descripción

El módulo de Cecabank para Prestashop permite realizar cobros a tus clientes utilizando el TPV de Cecabank.

# Instalación manual

El método de instalación manual se refiere a descargar nuestro plugin y subirlo a través del Dashboard de Prestashop vía ftp a su servidor. Prestashop tiene un artículo con [con las instrucciones de como hacerlo aquí](https://addons.prestashop.com/en/content/13-installing-modules).

# Actualización

Automáticamente se realizarán las actualizaciones y funcionarán de manera normal; de todas formas, siempre asegúrese de realizar un backup a su sitio por si acaso.

# Uso

Para utilizar el plugin usted necesita tener acceso a un TPV de Cecabank para poder obtener las credenciales.

# Comunicación online (URL de notificación)

La pasarela comunica el resultado del pago a la tienda mediante una petición servidor a servidor. La URL que hay que configurar como "URL de comunicación online" en la consola de Cecabank se muestra en la página de configuración del módulo y tiene esta forma:

```
https://su-tienda.com/index.php?fc=module&module=cecabank&controller=validation
```

Con URLs amigables activadas también responde en `https://su-tienda.com/module/cecabank/validation`.

**PrestaShop 9:** el núcleo incorpora un fichero `modules/.htaccess` que bloquea el acceso directo a los ficheros `.php` de los módulos. La URL antigua `https://su-tienda.com/modules/cecabank/validation.php` responde 403 y el pedido no se registra. Si su tienda usa PrestaShop 9 o superior, sustituya la URL antigua por la nueva en la consola de Cecabank. La URL antigua sigue funcionando en PrestaShop 1.6, 1.7 y 8.

Si la tienda está en modo mantenimiento, añada las IP de Cecabank a la lista de IP permitidas en mantenimiento para que la notificación pueda procesarse.

