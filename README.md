# php_webdav
simple php webdav server

but now it can be used in windows explorer . I am still trying to solve the problem.

use method:
 - put the webdav.php file in your php server
 - make a dir named 'public'
 - use apache or nginx to rename the request path

nginx:
```conf
rewrite ^/webdav\.php(.*) /webdav.php?path=$1;
```
apache:
```.htaccess
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteRule ^webdav\.php(/.*)$ /webdav.php?path=$1 [QSA,L]
</IfModule>
```
