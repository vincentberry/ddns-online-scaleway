<?php

// Récupération des clés d'API et d'autres paramètres depuis les variables d'environnement
$Online_Token = getenv('ONLINE_TOKEN');
$domains = explode(',', getenv('DOMAINS')) ?: [''];
$subdomains = explode(',', getenv('SUBDOMAINS')) ?: ['@', '*'];
$types = 'A';
$checkPublicIPv4 = getenv('CHECK_PUBLIC_IPv4') ?: 'true';
$logFilePath = getenv('LOG_FILE_PATH') ?: "/usr/src/app/log/log.log";

function writeToLog($message)
{
    global $logFilePath;
    file_put_contents($logFilePath, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    print_r($message . " \n");

    // Si le message contient "Fatal", arrêter l'exécution du script
    if (stripos($message, 'Fatal') !== false) {
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
        writeToLog("❌ Erreur cURL $httpCode : $error");
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
        writeToLog("❌ Erreur API Online.net : " . $errorCodes[$httpCode] . "\n");
    } else {
        writeToLog("❌ Erreur API Online.net : Code d'erreur inconnu ($httpCode)\n");
    }
}

// Fonction pour vérifier la connexion Internet
function checkInternetConnection()
{
    $connected = @fsockopen("www.google.com", 80);
    if ($connected) {
        fclose($connected);
        writeToLog("✅ Connexion Internet valide");
        return true;
    }
    writeToLog("❌ Erreur : Pas de connexion Internet.");
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
                writeToLog("❌ L'adresse IP récupérée n'est pas une adresse IPv4 publique : $IPv4");
                return false;
            }
        }
        writeToLog("✅ L'adresse IP récupérée est une adresse IPv4 publique");
    }
    return true;
}

writeToLog("\n---------------------------------");
writeToLog("🚩 Script Start");
writeToLog("💲ONLINE_TOKEN: " . $Online_Token);
writeToLog("💲domains: " . json_encode($domains));
writeToLog("💲subdomains: " . json_encode($subdomains));
writeToLog("💲type: " . $types);
writeToLog("💲checkPublicIPv4: " . $checkPublicIPv4);
writeToLog("💲logFilePath: " . $logFilePath);

// Vérification des valeurs des variables d'environnement
if (empty($Online_Token) || empty($domains) || empty($subdomains) || empty($types) || empty($logFilePath)) {
    writeToLog("⛔ Fatal : Veuillez fournir des valeurs valides pour les variables d'environnement.");
} else {
    writeToLog("✅ Variables d'environnement valide");
}

//vérification de la connection internet
if (!checkInternetConnection()) {
    writeToLog("⛔ Fatal : Veuillez vérifier votre connexion Internet pour l'initialisation.");
}

// Vérification de l'API Online.net
$userInfo = OnlineApi("user", "");

if ($userInfo === null) {
    writeToLog("⛔ Fatal : Vérification de l'API Online.net a échoué.");
} else {
    writeToLog("✅ API Online.net valide de " . $userInfo['last_name'] . " " . $userInfo['first_name'] . " \n");
}

while (true) {
    // Récupération de l'IPv4 du client appelant la page.
    $IPv4ApiResponse = @file_get_contents("https://api.ipify.org?format=json");
    $IPv6ApiResponse = @file_get_contents("https://api6.ipify.org?format=json");

    if ($IPv4ApiResponse !== false) {
        $IPv4Data = json_decode($IPv4ApiResponse, true);
        $addressIPv4 = $IPv4Data['ip'];
        writeToLog("🌐 Adresse IPv4 publique actuelle : $addressIPv4");

        if (isPublicIPv4($addressIPv4, $checkPublicIPv4)) {
            writeToLog("\n");

            foreach ($domains as $domain) {
                foreach ($subdomains as $sub) {

                    writeToLog("🔍 Vérification de l'IPv4 pour $sub.$domain...");

                    if ($sub === "@") {
                        $IPv4_domain = gethostbyname($domain); // Récupération de l'IPv4 en service sur l'enregistrement DNS.
                    } elseif ($sub === "*") {
                        $IPv4_domain = gethostbyname("testdnsall." . $domain); // Récupération de l'IPv4 en service sur l'enregistrement DNS.
                    } else {
                        $IPv4_domain = gethostbyname("$sub.$domain"); // Récupération de l'IPv4 en service sur l'enregistrement DNS.
                    }

                    writeToLog("📊 IPv4 publique actuelle : $addressIPv4");
                    writeToLog("📌 IPv4 publique enregistrée : $IPv4_domain");

                    if ($IPv4_domain !== $addressIPv4) { // Comparaison de la nouvelle IPv4 et de celle en service.
                        $ch = curl_init();

                        $URL =  "domain/" . $domain . "/version/active";
                        $POSTFIELDS = "[{\"name\": \"$sub\",\"type\": \"$types\",\"changeType\": \"REPLACE\",\"records\": [{\"name\": \"$sub\",\"type\": \"$types\",\"priority\": 0,\"ttl\": 3600,\"data\": \"$addressIPv4\"}]}]";
                        $result = OnlineApi($URL, $POSTFIELDS, "PATCH");

                        if ($result === null) {
                            writeToLog("⏰ Erreur ENVOI pour $sub.$domain");
                        } else {
                            writeToLog("✅ IPv4 publique à mise à jour avec succès pour $sub.$domain\n");
                        }
                    } else {
                        writeToLog("🔄 IPv4 inchangée pour $sub.$domain !\n");
                    }
                }
            }
        }
    } else {
        $error = error_get_last();
        writeToLog("❌ Impossible de récupérer l'adresse IPv4. Erreur : " . $error['message']);

        if (checkInternetConnection()) {
            writeToLog("❌ Erreur : La connexion Internet fonctionne, mais une erreur est survenue avec l'API ipify.");
        }
    }
    writeToLog("⏳ Attente de 5 minutes...");
    writeToLog("---------------------------------");

    // Pause de 5 minutes
    sleep(300);
}

writeToLog("⛔ Done !");
die("⛔ Done !\n");