# Serving WebP from a Laravel Nginx site

**NOTE: This document only applies to the upcoming 2.0 version**

This should work with most php sites although I'm basing the Nginx configuration around what's commonly seen with Laravel installations.

Create webp converter script in ```project_root/public/webp-on-demand.php```

```
<?php

require '../vendor/autoload.php';

use WebPConvert\WebPConvert;

$source = __DIR__ . $_GET['source'];
$destination = $source . '.webp';

WebPConvert::serveConverted($source, $destination, [
  'fail' => 'original',     // If failure, serve the original image (source). Other options include 'throw', '404' and 'report'
  // 'show-report' => true,  // Generates a report instead of serving an image

  'serve-image' => [
    'headers' => [
      'cache-control' => true,
      'vary-accept' => true,
      // other headers can be toggled...
    ],
    'cache-control-header' => 'max-age=2',
  ],

'convert' => [
  // all convert option can be entered here (ie "quality")
  ],
]);

```


### Configure Nginx

We just need to add the following block to our site in ```/etc/sites-enabled/```

```
location ~* ^/.*\.(png|jpe?g)$ {
  add_header Vary Accept;
  expires 365d;
  if ($http_accept !~* "webp"){
    break;
  }
  try_files
    $uri.webp
    /webp-on-demand.php?source=$uri
    ;
}
```

Then reload Nginx ```sudo systemctl restart nginx```

The full Nginx block should look like 

```
server {
    server_name webp-testing.com;
    root /home/forge/webp-testing.com/public;

    index index.html index.htm index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~* ^/.*\.(png|jpe?g)$ {
        add_header Vary Accept;
        expires 365d;
        if ($http_accept !~* "webp"){
          break;
        }
        try_files
          $uri.webp
          /webp-on-demand.php?source=$uri
          ;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log off;
    error_log  /var/log/nginx/webp-testing.com-error.log error;

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php7.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
 
    # cache static assets
    location ~*  \.(gif|ico|css|pdf|svg)$ {
        expires 365d;
    }
    
    location ~*  \.(js)$ {
        add_header Cache-Control no-cache;
    }
    
}
```
