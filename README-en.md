# DDNS (Dynamic DNS) Update Script for Online.net and Scaleway

[![FR](https://img.shields.io/badge/langue-FR-blue)](./README.md)
[![EN](https://img.shields.io/badge/language-EN-red)](./README-en.md)

## Objective

This script aims to automate DNS record updates for a specified subdomain with the online service provider Online.net. This is particularly useful in scenarios where the host's IP address changes regularly.

## Features

1. **Provider Compatibility:** The script is designed to work with Online.net APIs.
2. **API-based comparison:** The script reads the active zone through the Online.net API and updates only records whose address differs from the current public IP.
3. **Easy Configuration:** Users can easily configure their credentials, the subdomain to be updated, etc., by modifying variables in the script.
4. **Activity Log:** The script logs significant events in a log file for easy tracking and troubleshooting.

### Local DNS and repeated updates

The comparison uses the `A` and `AAAA` records in the active Online.net zone, read once per domain on each cycle. It does not depend on the container's DNS resolver, so private addresses returned by local DNS or stale DNS caches do not trigger unnecessary writes. The `*` wildcard is read directly from the zone, and equivalent representations of the same IPv6 address are recognized.

If reading the zone fails, that domain is skipped until the next cycle without sending any updates. A successful update message means the API accepted the change; DNS caches may retain the previous value until its TTL expires.

Offline regression tests: `php tests/ddns_update_test.php`.

## Configuration

After obtaining changes, rebuild and recreate the Compose service configured with `build: .` to apply them:

```bash
docker compose build --pull ddns
docker compose up -d --force-recreate ddns
```

An older DockerHub image must also be rebuilt and published before installations using it can be updated.

Before using the script, users need to configure parameters specific to their environment. Typical parameters include:

- API credentials for Online.net
- Root domain name.
- Subdomain to update.
- Log file path.

## Online.net API Documentation

To obtain the necessary API credentials, please follow these steps:

1. Go to Online.net's API access page: [https://console.online.net/en/api/access](https://console.online.net/en/api/access).
2. Log in to your Online.net account.
3. On the API access page, you'll find the required information, including your "Access Key" (API Key).
4. Copy your "Access Key" and use it to configure the corresponding variable in the `ddns_update.php` script.

Make sure to consult the full documentation for details on using the Online.net API: [API Doc Online.](https://console.online.net/en/api).

## Notes

The script is provided as-is and should be adapted to the specific needs of the user.
Be careful not to store API credentials insecurely.
Ensure that the server running the script has Internet access to make requests to the provider's APIs.

## Using the Docker Image with Docker Compose (recommended, [DockerHub](https://hub.docker.com/r/vincentberry/ddns-online-scaleway))

```yaml
version: '3'
services:
  ddns:
    image: vincentberry/ddns-online-scaleway
    environment:
      - ONLINE_TOKEN=MyOnlineNetToken
      - DOMAINS=example.com,example-2.com
      - SUBDOMAINS=@,*
      - TYPES=A
      - CHECK_PUBLIC_IPv4=true #optional
      - LOG_LEVEL=INFO #optional
    restart: unless-stopped
```

Logs are stored in Docker at `/usr/src/app/log`.
DNS can be specified if needed for ping.

```yaml
version: '3'
services:
  ddns:
    image: vincentberry/ddns-online-scaleway
    volumes:
      - /MyFolder/log:/usr/src/app/log
    environment:
      - ONLINE_TOKEN=MyOnlineNetToken
      - DOMAINS=example.com,example-2.com
      - SUBDOMAINS=@,*
      - TYPES=A
      - CHECK_PUBLIC_IPv4=true #optional
      - CHECK_PUBLIC_IP6=true #optional
      - LOG_LEVEL=INFO #optional
    dns:
      -8.8.8.8
      -2001:4860:4860::8888
    restart: unless-stopped
```

## Using the Script with Docker Compose (without DockerHub)

1. Download the Script: Download the repository, including `ddns_update.php` and the `Dockerfile`. Place the Compose file in the same directory.

2. Configuration: Open the script in a text editor and configure the variables at the beginning of the script as needed (credentials, subdomain, etc.).

```yaml
version: '3'
services:
  ddns:
    build: .
    volumes:
      - /docker/ddns/log:/usr/src/app/log
    working_dir: /usr/src/app
    environment:
      - ONLINE_TOKEN=MyOnlineNetToken
      - DOMAINS=example.com,example-2.com
      - SUBDOMAINS=@,*
      - TYPES=A
      - CHECK_PUBLIC_IPv4=true #optional
      - CHECK_PUBLIC_IP6=true #optional
      - LOG_LEVEL=INFO #optional
    dns:
      -8.8.8.8
      -2001:4860:4860::8888
    command: ["php", "/usr/src/app/ddns_update.php"]
    restart: unless-stopped
```

```bash
php ddns_update.php
```

| Variable             | Type     | Description                                                                                                                                         |
| -------------------- | :------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ONLINE_TOKEN`       | `string` | Replace "MyOnlineNetToken" with your Online.net API key. Obtain this key from [the Online.net console](https://console.online.net/en/api/access). |
| `DOMAINS`            | `string` | Enter your domain list separated by commas, e.g., `example.com,example-2.com`.                                                                      |
| `SUBDOMAINS`         | `string` | Specify the subdomains to update, separated by commas. Use `@` for the main domain and `*` for all subdomains. *Default: `*,@`*                    |
| `TYPES`              | `A` or `AAAA` or `A,AAAA` | Specify the DNS record type to update, e.g., `A` (default) for an IPv4 address record. *Default: `A,AAAA`*                                      |
| `CHECK_PUBLIC_IPv4`  | `boolean` | **Optional** If set to `true`, verifies that the retrieved IP address is public. Otherwise, this verification is ignored. *Default: `true`*        |
| `CHECK_PUBLIC_IPv6`  | `boolean` | **Optional** If set to `true`, verifies that the retrieved IP address is public. Otherwise, this verification is ignored. *Default: `true`*        |
| `LOG_PATH`           | `string` | **Optional** The path for log storage. To store logs in a volume, specify the path here (e.g., `/log`).                                            |
| `LOG_LEVEL`          | `string` | **Optional** Sets the log level for the application. Possible values are: `DEBUG`, `INFO`, `ERROR`, `FATAL`. Default is `DEBUG`.                  |
| `LOOP_INTERVAL`      | `int`    | **Optional** Duration (in seconds) between each main loop iteration. Defines how long the script waits before repeating checks. Default is `300`.  |

### Log Levels

The available log levels in the application are as follows:

| Level   | Description                                           |
| ------- | ----------------------------------------------------- |
| `DEBUG` | Detailed information for debugging purposes.         |
| `INFO`  | General information on program execution.            |
| `ERROR` | Error messages reporting issues that need resolving. |
| `FATAL` | Critical errors causing application termination.     |

---

Ce README a été créé avec le soutien d'une intelligence artificielle pour fournir des informations claires et utiles.
