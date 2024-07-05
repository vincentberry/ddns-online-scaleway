<?php

// Récupération des clés d'API et d'autres paramètres depuis les variables d'environnement
$Online_Token = getenv('ONLINE_TOKEN');
$domains = explode(',', getenv('DOMAINS')) ?: [''];
$subdomains = explode(',', getenv('SUBDOMAINS')) ?: ['@', '*'];
$types = getenv('TYPES') ?: 'A';
$logFilePath = getenv('LOG_FILE_PATH') ?: "/usr/src/app/log/log.log";

function writeToLog($message)
{
    global $logFilePath;
    file_put_contents($logFilePath, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    print_r($message . "\n");
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
    }elseif ($httpCode == 401 && isset($result['code'])){
        ApiErrorOnline($result['code']);
        return null;
    }else{
        writeToLog("❌ Erreur cURL $httpCode : $error\n");
        return null;
    }
}

// Fonction pour gérer les erreurs spécifiques à l'API Online.net
function ApiErrorOnline($httpCode) {
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
        writeToLog("✅ Connexion Internet valide\n");
        return true;
    }
    writeToLog("❌ Erreur : Pas de connexion Internet.\n");
    return false;
}

writeToLog("\n---------------------------------\n");
writeToLog("🚩 Script Start\n");
writeToLog("💲ONLINE_TOKEN: " . $Online_Token . "\n");
writeToLog("💲domains: " . json_encode($domains) . "\n");
writeToLog("💲subdomains: " . json_encode($subdomains) . "\n");
writeToLog("💲type: " . $types . "\n");
writeToLog("💲logFilePath: " . $logFilePath . "\n");

// Vérification des valeurs des variables d'environnement
if (empty($Online_Token) || empty($domains) || empty($subdomains) || empty($types) || empty($logFilePath)) {
    writeToLog("⛔ Fatal : Veuillez fournir des valeurs valides pour les variables d'environnement.\n");
    die("⛔ Done !");
}else{
    writeToLog("✅ Variables d'environnement valide\n");
}

//vérification de la connection internet
if (!checkInternetConnection()) {
    writeToLog("❌ Fatal : Veuillez vérifier votre connexion Internet pour l'initialisation.\n");
    die("⛔ Done !");
}

// Vérification de l'API Online.net
$userInfo = OnlineApi("user", "");

if ($userInfo === null) {
    writeToLog("⛔ Fatal : Vérification de l'API Online.net a échoué.\n");
    die("⛔ Done !\n");
}else{
    writeToLog("✅ API Online.net valide de ".$userInfo['last_name'] . " " . $userInfo['first_name']." \n\n");
}

while (true) {
    foreach ($domains as $domain) {
        foreach ($subdomains as $sub) {
            // Récupération de l'IP du client appelant la page.
            $ipApiResponse = @file_get_contents("https://api64.ipify.org?format=json");

            if ($ipApiResponse !== false) {
                $ipData = json_decode($ipApiResponse, true);
                $address = $ipData['ip'];

                writeToLog("🌐 Adresse IP actuelle : $address\n");
            } else {
                $error = error_get_last();
                writeToLog("❌ Impossible de récupérer l'adresse IP. Erreur : " . $error['message'] . "\n");

                if (checkInternetConnection()) {
                    writeToLog("❌ Erreur : La connexion Internet fonctionne, mais une erreur est survenue avec l'API ipify.\n");
                }
            }

            writeToLog("🔍 Vérification de l'IP pour $sub.$domain...\n");

            if ($sub === "@") {
                $ipyet = gethostbyname($domain); // Récupération de l'IP en service sur l'enregistrement DNS.
            } elseif ($sub === "*") {
                $ipyet = gethostbyname("testdnsall." . $domain); // Récupération de l'IP en service sur l'enregistrement DNS.
            } else {
                $ipyet = gethostbyname("$sub.$domain"); // Récupération de l'IP en service sur l'enregistrement DNS.
            }

            writeToLog("📊 IP actuelle : $address\n");
            writeToLog("📌 IP enregistrée : $ipyet\n");

            if ($ipyet !== $address) { // Comparaison de la nouvelle IP et de celle en service.
                $ch = curl_init();

                $URL =  "domain/" . $domain . "/version/active";
                $POSTFIELDS = "[{\"name\": \"$sub\",\"type\": \"$types\",\"changeType\": \"REPLACE\",\"records\": [{\"name\": \"$sub\",\"type\": \"$types\",\"priority\": 0,\"ttl\": 3600,\"data\": \"$address\"}]}]";
                $result = OnlineApi($URL, $POSTFIELDS, "PATCH");

                if ($result === null) {
                    writeToLog("⏰ Erreur ENVOI pour $sub.$domain" . "\n");
                } else {
                    writeToLog("✅ IP mise à jour avec succès pour $sub.$domain\n\n");
                }

            } else {
                writeToLog("🔄 IP inchangée pour $sub.$domain !\n\n");
            }
        }
    }

    writeToLog("⏳ Attente de 5 minutes...\n---------------------------------\n");

    // Pause de 5 minutes
    sleep(300);
}

writeToLog("⛔ Done !\n");
die("⛔ Done !\n");
?>
