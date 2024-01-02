<?php

$ONLINE_API = 'ddc2563b337ad12b16b5e7ae946622c1e3c43ec0';

// Tableaux de domaines et sous-domaines
$domains = ['domaine1.fr', 'domaine2.fr'];
$subdomains = ['subdomain1', 'subdomain2']; //@ pour root,  * pour all
$types = 'A';
$logFilePath = "/log/log.log";

function writeToLog($message)
{
    global $logFilePath;
    file_put_contents($logFilePath, date('Y-m-d H:i:s') . " - $message", FILE_APPEND);
    print_r($message);
}

writeToLog("\n\n---------------------------------\n\n");
writeToLog("⏰ Script Start\n");

while (true) {
    foreach ($domains as $domain) {
        foreach ($subdomains as $sub) {
            // Récupération de l'Ip du client appelant la page.
            $ipApiResponse = file_get_contents("https://api64.ipify.org?format=json");

            if ($ipApiResponse !== false) {
                $ipData = json_decode($ipApiResponse, true);
                $address = $ipData['ip'];

                writeToLog("🌐 Adresse IP actuelle : $address\n");
            } else {
                writeToLog("❌ Impossible de récupérer l'adresse IP.\n");
            }

            writeToLog("🔍 Vérification de l'IP pour $sub.$domain...\n");

            if ($sub === "@") {
                $ipyet = gethostbyname($domain); // Récupération de l'Ip en service sur l'enregistrement DNS.
            } elseif ($sub === "*") {
                $ipyet = gethostbyname("testdnsall." . $domain); // Récupération de l'Ip en service sur l'enregistrement DNS.
            } else {
                $ipyet = gethostbyname("$sub.$domain"); // Récupération de l'Ip en service sur l'enregistrement DNS.
            }

            writeToLog("📊 IP actuelle : $address\n");
            writeToLog("📌 IP enregistrée : $ipyet\n");

            if ($ipyet !== $address) { // Comparaison de la nouvelle Ip et de celle en service.
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, "https://api.online.net/api/v1/domain/" . $domain . "/version/active");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "[{\"name\": \"$sub\",\"type\": \"$types\",\"changeType\": \"REPLACE\",\"records\": [{\"name\": \"$sub\",\"type\": \"$types\",\"priority\": 0,\"ttl\": 3600,\"data\": \"$address\"}]}]");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');

                $headers = array();
                $headers[] = 'Authorization: Bearer ' . $ONLINE_API;
                $headers[] = 'Content-Type: application/json'; // Correction du type de contenu
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $result = curl_exec($ch);

                if (curl_errno($ch)) {
                    writeToLog("⏰ Erreur ENVOI pour $sub.$domain : " . curl_error($ch) . "\n");
                } else {
                    writeToLog("✅ IP mise à jour avec succès pour $sub.$domain\n\n");
                }

                curl_close($ch);
            } else {
                writeToLog("🔄 IP inchangée pour $sub.$domain !\n\n");
            }
        }
    }

    writeToLog("⏳ Attente de 5 minutes...\n---------------------------------\n");

    // Pause de 5 minutes
    sleep(300);
}
?>
