# php_webdav
simple php webdav server
use method:
  put the webdav.php file in your php server
  make a dir named 'public'
  use apache or nginx to rename the request path

nginx:
  rewrite ^/webdav\.php(.*) /webdav.php?path=$1;
apache:
  
