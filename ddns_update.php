<?php

// Fonction centralisée pour récupérer les variables d'environnement avec valeur par défaut
function getEnvVar($key, $default) {
    return getenv($key) ?: $default;
}

// Récupération des paramètres depuis les variables d'environnement
$Online_Token = getEnvVar('ONLINE_TOKEN', '');
$domains = explode(',', getEnvVar('DOMAINS', ''));
$subdomains = explode(',', getEnvVar('SUBDOMAINS', '@'));
$types = explode(',', getEnvVar('TYPES', 'A,AAAA'));
$checkPublicIPv4 = filter_var(getEnvVar('CHECK_PUBLIC_IPv4', 'true'), FILTER_VALIDATE_BOOLEAN);
$checkPublicIPv6 = filter_var(getEnvVar('CHECK_PUBLIC_IPv6', 'true'), FILTER_VALIDATE_BOOLEAN);
$logFilePath = getEnvVar('LOG_FILE_PATH', "/usr/src/app/log/log.log");
$loopInterval = intval(getEnvVar('LOOP_INTERVAL', 300));
$logLevel = getEnvVar('LOG_LEVEL', "DEBUG");

// Définition des niveaux de log
$logLevels = [
    "DEBUG" => 0,
    "INFO" => 1,
    "ERROR" => 2,
    "FATAL" => 3,
];

function writeToLog($type, $message)
{
    global $logFilePath;
    global $logLevel;
    global $logLevels;

    // Vérifie si le niveau du message est égal ou supérieur au niveau actuel
    if ($logLevels[$type] >= $logLevels[$logLevel]) {
        print_r("[$type] $message\n");
    }

    file_put_contents($logFilePath, date('Y-m-d H:i:s') . " [$type] - $message\n", FILE_APPEND);

    // Si le message contient "Fatal", arrêter l'exécution du script
    if (stripos($type, 'FATAL') !== false) {
        die("⛔ Script arrêté\n");
    }
}

// Fonction pour vérifier l'API Online.net
function OnlineApi($URL, $POSTFIELDS = "", $method = 'GET')
{
    global $Online_Token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.online.net/api/v1/$URL");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTFIELDS);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = array();
    $headers[] = 'Authorization: Bearer ' . $Online_Token;
    $headers[] = 'Content-Type: application/json'; // Correction du type de contenu
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result =  json_decode(curl_exec($ch), true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);
    if ($httpCode == 200 || $httpCode == 201 || $httpCode == 202 || $httpCode == 203 || $httpCode == 204) {
        return  $result ?: "ok";
    } elseif ($httpCode == 401 && isset($result['code'])) {
        ApiErrorOnline($result['code']);
        return null;
    } else {
        writeToLog("ERROR", "❌ Erreur cURL $httpCode : $error");
        return null;
    }
}

// Fonction pour gérer les erreurs spécifiques à l'API Online.net
function ApiErrorOnline($httpCode)
{
    $errorCodes = [
        -1 => 'Erreur interne',
        1 => 'Paramètre manquant',
        2 => 'Mauvaise valeur de paramètre',
        3 => 'Méthode inconnue',
        4 => 'Méthode non autorisée',
        5 => 'Mauvaise requête',
        6 => 'Pas encore implémenté',
        7 => 'Ressource introuvable',
        8 => 'Ressource non atteignable',
        9 => 'Permission refusée',
        10 => 'Action déjà effectuée',
        11 => 'L\'utilisateur a des factures impayées',
        12 => 'Trop de requêtes',
        13 => 'Ressource en cours de création',
        16 => 'Ressource occupée',
        17 => 'Conflit',
    ];

    if (isset($errorCodes[$httpCode])) {
        writeToLog("ERROR", "❌ Erreur API Online.net : " . $errorCodes[$httpCode] . "\n");
    } else {
        writeToLog("ERROR", "❌ Erreur API Online.net : Code d'erreur inconnu ($httpCode)\n");
    }
}

// Fonction pour vérifier la connexion Internet
function checkInternetConnection()
{
    $connected = @fsockopen("www.google.com", 80);
    if ($connected) {
        fclose($connected);
        writeToLog("DEBUG", "✅ Connexion Internet valide");
        return true;
    }
    writeToLog("ERROR", "❌ Erreur : Pas de connexion Internet.");
    return false;
}

// Fonction pour vérifier si c'est bien une IPv4 Public
function isPublicIPv4($IPv4, $checkPublicIPv4)
{
    if ($checkPublicIPv4) {
        // Plages d'adresses IP privées
        $privateRanges = [
            '10.0.0.0|10.255.255.255',    // 10.0.0.0 - 10.255.255.255
            '172.16.0.0|172.31.255.255',  // 172.16.0.0 - 172.31.255.255
            '192.168.0.0|192.168.255.255' // 192.168.0.0 - 192.168.255.255
        ];

        // Convertit l'adresse IP en entier pour la comparaison
        $IPv4Long = ip2long($IPv4);

        foreach ($privateRanges as $range) {
            list($start, $end) = explode('|', $range);
            if ($IPv4Long >= ip2long($start) && $IPv4Long <= ip2long($end)) {
                writeToLog("ERROR", "❌ L'adresse IP récupérée n'est pas une adresse IPv4 publique : $IPv4");
                return false;
            }
        }
        writeToLog("DEBUG", "✅ L'adresse IP récupérée est une adresse IPv4 publique");
    }
    return true;
}

// Fonction pour vérifier si c'est bien une IPv6 Publique
function isPublicIPv6($IPv6, $checkPublicIPv6)
{
    if ($checkPublicIPv6) {
        // Détection simplifiée de l'adresse IPv6 publique (non exhaustif)
        // Vérification si l'adresse IPv6 commence par les préfixes typiques des adresses publiques
        $publicPrefixes = [
            '2',    // Global Unicast (ULA)
            '3',    // Global Unicast (ULA)
            '4',    // Global Unicast (ULA)
            '5',    // Global Unicast (ULA)
            '6',    // Global Unicast (ULA)
            '7',    // Global Unicast (ULA)
            '8',    // Global Unicast (ULA)
            '9',    // Global Unicast (ULA)
            'a',    // Global Unicast
            'A',    // Global Unicast
            'b',    // Global Unicast
            'B',    // Global Unicast
            'c',    // Global Unicast
            'C',    // Global Unicast
            'd',    // Global Unicast
            'D',    // Global Unicast
            'e',    // Global Unicast
            'E',    // Global Unicast
            'f',    // Global Unicast
            'F'     // Global Unicast
        ];

        // Vérification du préfixe
        $firstChar = substr($IPv6, 0, 1);
        if (in_array($firstChar, $publicPrefixes)) {
            writeToLog("DEBUG", "✅ L'adresse IP récupérée est une adresse IPv6 publique : $IPv6");
            return true;
        }

        writeToLog("ERROR", "❌ L'adresse IP récupérée n'est pas une adresse IPv6 publique : $IPv6");
        return false;
    }
    return true;
}

// Fonction pour comparer et mettre à jour les adresses IP enregistrées
function compareAndUpdate($IP, $IP_domain, $addressIP, $domain, $sub, $types)
{
    writeToLog("DEBUG", "📊 IP$IP publique actuelle : $addressIP");
    writeToLog("DEBUG", "📌 IP$IP publique enregistrée : $IP_domain");

    if ($IP_domain !== $addressIP) { // Comparaison de la nouvelle IPv4 et de celle en service.
        $URL =  "domain/" . $domain . "/version/active";
        $POSTFIELDS = "[{\"name\": \"$sub\",\"type\": \"$types\",\"changeType\": \"REPLACE\",\"records\": [{\"name\": \"$sub\",\"type\": \"$types\",\"priority\": 0,\"ttl\": 3600,\"data\": \"$addressIP\"}]}]";
        $result = OnlineApi($URL, $POSTFIELDS, "PATCH");

        if ($result === null) {
            writeToLog("ERROR","⏰ Échec de l'envoi de la mise à jour DNS pour le sous-domaine $sub du domaine $domain.");
        } else {
            writeToLog("INFO", "✅ Mise à jour réussie : La nouvelle IP publique a été appliquée pour le sous-domaine $sub du domaine $domain.");
        }
    } else {
        writeToLog("DEBUG", "🔄 IP $IP inchangée pour le sous-domaine $sub du domaine $domain.");
    }
}

writeToLog("INFO", "---------------------------------");
writeToLog("INFO", "🚩 Script Start");
writeToLog("INFO", "💲ONLINE_TOKEN: " . $Online_Token);
writeToLog("INFO", "💲domains: " . json_encode($domains));
writeToLog("INFO", "💲subdomains: " . json_encode($subdomains));
writeToLog("INFO", "💲type: " . json_encode($types));
if (in_array('A', $types)) {
    writeToLog("INFO", "💲checkPublicIPv4: " . $checkPublicIPv4);
}
if (in_array('AAAA', $types)) {
    writeToLog("INFO", "💲checkPublicIPv6: " . $checkPublicIPv6);
}
writeToLog("INFO", "💲logFilePath: " . $logFilePath);

// Vérification des valeurs des variables d'environnement
if (empty($Online_Token) || empty($domains) || empty($subdomains) || empty($types) || empty($logFilePath)) {
    writeToLog("FATAL", "⛔ Veuillez fournir des valeurs valides pour les variables d'environnement.");
} else {
    writeToLog("INFO", "✅ Variables d'environnement valide");
}

//vérification de la connection internet
if (!checkInternetConnection()) {
    writeToLog("FATAL", "⛔ Veuillez vérifier votre connexion Internet pour l'initialisation.");
}

// Vérification de l'API Online.net
$userInfo = OnlineApi("user", "");

if ($userInfo === null) {
    writeToLog("FATAL", "⛔ Vérification de l'API Online.net a échoué.");
} else {
    writeToLog("INFO", "✅ API Online.net valide de " . $userInfo['last_name'] . " " . $userInfo['first_name']);
}

while (true) {

    //IPv4 Active
    if (in_array('A', $types)) {
        // Récupération de l'IPv4 du client appelant la page.
        $IPv4ApiResponse = @file_get_contents("https://api.ipify.org?format=json");
        if ($IPv4ApiResponse !== false) {
            $IPv4Data = json_decode($IPv4ApiResponse, true);
            $addressIPv4 = $IPv4Data['ip'];
            writeToLog("DEBUG", "🌐 Adresse IPv4 publique actuelle : $addressIPv4");

            if (isPublicIPv4($addressIPv4, $checkPublicIPv4)) {

                foreach ($domains as $domain) {
                    foreach ($subdomains as $sub) {

                        writeToLog("DEBUG", "🔍 Vérification de l'IPv4 pour $sub.$domain...");

                        if ($sub === "@") {
                            $IPv4_domain = gethostbyname($domain); // Récupération de l'IPv4 en service sur l'enregistrement DNS.
                        } elseif ($sub === "*") {
                            $IPv4_domain = gethostbyname("testdnsall." . $domain); // Récupération de l'IPv4 en service sur l'enregistrement DNS.
                        } else {
                            $IPv4_domain = gethostbyname("$sub.$domain"); // Récupération de l'IPv4 en service sur l'enregistrement DNS.
                        }
                        compareAndUpdate("v4", $IPv4_domain, $addressIPv4, $domain, $sub, "A");
                    }
                }
            }
        } else {
            $error = error_get_last();
            writeToLog("ERROR", "❌ Impossible de récupérer l'adresse IPv4. Erreur : " . $error['message']);

            if (checkInternetConnection()) {
                writeToLog("ERROR", "❌ Erreur : La connexion Internet fonctionne, mais une erreur est survenue avec l'API ipify.");
            }
        }
    }

    //IPv6 Active
    if (in_array('AAAA', $types)) {
        // Récupération de l'IPv6 du client appelant la page.
        $IPv6ApiResponse = @file_get_contents("https://api6.ipify.org?format=json");
        if ($IPv6ApiResponse !== false) {
            $IPv6Data = json_decode($IPv6ApiResponse, true);
            $addressIPv6 = $IPv6Data['ip'];
            writeToLog("DEBUG", "🌐 Adresse IPv4 publique actuelle : $addressIPv6");

            if (isPublicIPv4($addressIPv6, $checkPublicIPv6)) {

                foreach ($domains as $domain) {
                    foreach ($subdomains as $sub) {

                        writeToLog("DEBUG", "🔍 Vérification de l'IPv6 pour $sub.$domain...");

                        $IPv6_domain = "";
                        compareAndUpdate("v6", $IPv6_domain, $addressIPv6, $domain, $sub, "AAAA");
                    }
                }
            }
        } else {
            $error = error_get_last();
            writeToLog("ERROR", "❌ Impossible de récupérer l'adresse IPv6. Erreur : " . $error['message']);

            if (checkInternetConnection()) {
                writeToLog("ERROR", "❌ Erreur : La connexion Internet fonctionne, mais une erreur est survenue avec l'API ipify.");
            }
        }
    }

    writeToLog("DEBUG", "⏳ Attente de 5 minutes...");

    // Pause de 5 minutes
    sleep($loopInterval);
}

writeToLog("FATAL", "⛔ Script terminé !");
die("⛔ Fin de l'exécution.\n");