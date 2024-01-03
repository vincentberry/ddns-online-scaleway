# Utilisation de l'image PHP officielle avec support pour cURL
FROM php:8.2-cli

# Installation de l'extension cURL
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install -j$(nproc) curl

# Copie du script PHP dans le conteneur
COPY ddns_update.php /usr/src/app/ddns_update.php

# Création d'un répertoire pour les logs
RUN mkdir /usr/src/app/log

# Attribution des permissions nécessaires
RUN chmod 755 /usr/src/app/ddns_update.php

# Exécution du script
CMD ["sh", "-c", "php /usr/src/app/ddns_update.php"]
