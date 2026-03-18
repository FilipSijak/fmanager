FROM nginx AS base

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

FROM base AS deploy
COPY ./public /var/www/public
