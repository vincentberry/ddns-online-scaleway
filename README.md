# Script de Mise à Jour DDNS (Dynamic DNS) pour Online.net et Scaleway

## Objectif

Le script vise à automatiser la mise à jour des enregistrements DNS A pour un sous-domaine spécifié chez les fournisseurs de services en ligne tels que Online.net. Cela est particulièrement utile dans les scénarios où l'adresse IP de l'hôte change régulièrement.

⚠️ Veuillez noter que ce script est actuellement limité à la gestion des adresses IPv4 uniquement. Les adresses IPv6 ne sont pas prises en charge pour le moment. Assurez-vous que vos configurations et attentes sont alignées avec cette limitation. ⚠️

## Fonctionnalités

1. **Compatibilité Multi-Fournisseurs:** Le script est conçu pour fonctionner avec les API de plusieurs fournisseurs de services, en se concentrant sur Online.net.

2. **Utilisation de cURL:** Le script utilise cURL pour effectuer des requêtes HTTP pour connaitre la ip du dns.

3. **Configuration Facile:** Les utilisateurs peuvent configurer facilement leurs informations d'identification, le sous-domaine à mettre à jour, etc., en modifiant les variables dans le script.

4. **Log de l'Activité:** Le script enregistre les événements importants dans un fichier journal pour permettre un suivi et un dépannage faciles.

## Configuration

Avant d'utiliser le script, les utilisateurs doivent configurer les paramètres spécifiques à leur environnement. Les paramètres typiques incluent :

- Informations d'identification API pour Online.net
- Nom de domaine racine.
- Sous-domaine à mettre à jour.
- Chemin du fichier journal.

## Documentation API Online.net

Pour obtenir les informations d'identification API nécessaires, veuillez suivre les étapes ci-dessous :

1. Accédez à la page d'accès à l'API d'Online.net : [https://console.online.net/fr/api/access](https://console.online.net/fr/api/access).

2. Connectez-vous à votre compte Online.net.

3. Sur la page d'accès à l'API, vous trouverez les informations nécessaires, notamment votre "Clé d'accès" (API Key).

4. Copiez votre "Clé d'accès" et utilisez-la pour configurer la variable correspondante dans le script `ddns_update.php`.

Assurez-vous de consulter la documentation complète pour obtenir des détails sur l'utilisation de l'API Online.net : [Doc API Online.](https://console.online.net/fr/api).

## Remarques
Le script est fourni tel quel et doit être adapté aux besoins spécifiques de l'utilisateur.
Veillez à ne pas stocker les informations d'identification d'API de manière non sécurisée.
Assurez-vous que le serveur exécutant le script a accès à Internet pour effectuer des requêtes vers les API des fournisseurs.

## Comment Utiliser image docker avec Docker Compose (recommandé, [DockerHub](https://hub.docker.com/r/vincentberry/ddns-online-scaleway))

````Dockercompose.yml
version: '3'
services:
  ddns:
    image: vincentberry/ddns-online-scaleway
    environment:
      - ONLINE_TOKEN=MonTokenOniline.Net
      - DOMAINS=exemple.fr,exemple-2.fr
      - SUBDOMAINS=@,*
      - TYPES=A
      - CHECK_PUBLIC_IPv4=true #optional
    restart: unless-stopped
````
Les  logs sont stocké dans le docker `/usr/src/app/log`
Des DNS si besoin pour le ping peut être spécifié 

````DockercomposeDns.yml
version: '3'
services:
  ddns:
    image: vincentberry/ddns-online-scaleway
   volumes:
        - /MonDossier/log:/usr/src/app/log
    environment:
      - ONLINE_TOKEN=MonTokenOniline.Net
      - DOMAINS=exemple.fr,exemple-2.fr
      - SUBDOMAINS=@,*
      - TYPES=A
      - CHECK_PUBLIC_IPv4=true #optional
    dns:
      -8.8.8.8
    restart: unless-stopped
````

## Comment Utiliser le Script avec Docker Compose (sans dockerhub)
1. Téléchargement du Script: Téléchargez le script ddns_update.php depuis le référentiel GitHub.

2. Configuration: Ouvrez le script dans un éditeur de texte et configurez les variables au début du script selon vos besoins (informations d'identification, sous-domaine, etc.).

````Dockercompose.yml
version: '3'
services:
  ddns:
    image: php:7.4-cli
    volumes:
      - /docker/ddns/script:/usr/src/app
      - /docker/ddns/log:/usr/src/app/log
    working_dir: /script
    environment:
      - ONLINE_TOKEN=MonTokenOniline.Net
      - DOMAINS=exemple.fr,exemple-2.fr
      - SUBDOMAINS=@,*
      - TYPES=A
      - CHECK_PUBLIC_IPv4=true #optional
    dns:
      -8.8.8.8
    command: ["php", "/usr/src/app/ddns_update.php"]
    restart: unless-stopped
````

```bash
php ddns_update.php
```

| Variable              | Type     | Description                                                                                                    |
| --------------------- | :------- | -------------------------------------------------------------------------------------------------------------- |
| `ONLINE_TOKEN`        | `string` | Remplacez "MonTokenOniline.Net" par votre clé API Online.net. Obtenez cette clé depuis [la console Online.net](https://console.online.net/fr/api/access). |
| `DOMAINS`             | `string` | Indiquez la liste de vos domaines séparés par des virgules. Par exemple, `exemple.fr,exemple-2.fr`.             |
| `SUBDOMAINS`          | `string` | Spécifiez les sous-domaines séparés par des virgules que vous souhaitez mettre à jour. Utilisez `@` pour le domaine principal et `*` pour tous les sous-domaines (par exemple, `@,*`). |
| `TYPES`               | `A` or  `AAAA` or `A,AAAA` | Indiquez le type d'enregistrement DNS à mettre à jour. Par exemple, `A`  (par défaut) pour un enregistrement de type Adresse IPv4. |
| `CHECK_PUBLIC_IPv4`   | `boolean` | (Optionnel) Si défini à true (par défaut), vérifie que l'adresse IP récupérée est une adresse publique. Sinon, cette vérification est ignorée. |
| `LOG_PATH`            | `string` | (Optionnel) Le chemin du répertoire pour les logs. Si vous souhaitez les stocker dans un volume, spécifiez le chemin ici (par exemple, `/log`). |

Ce README a été créé avec le soutien d'une intelligence artificielle pour fournir des informations claires et utiles.
