FROM php:8.4.2-cli

WORKDIR /app
COPY . .
RUN mkdir -p data && chown -R www-data:www-data data

USER www-data
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
