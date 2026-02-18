FROM columbusinteractive/php-parallel:8.4

COPY ./docker/bootstrap.php /app/bootstrap.php

ENTRYPOINT ["php","/app/bootstrap.php"]