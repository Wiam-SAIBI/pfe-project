<?php
// Test simple de l'API des escales

// Données de test
$test_data = [
    'matricule_navire' => 'IMO-9803613',
    'nom_navire' => 'MSC Grandiosa',
    'date_accostage' => '2025-01-30T10:00',
    'date_sortie' => '2025-02-02T16:00'
];

echo "<h2>Test de l'API Escales</h2>";
echo "<h3>Données de test :</h3>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Test avec cURL
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'http://localhost:3309/gestion_res/api/escales.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($test_data))
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "<h3>Réponse du serveur :</h3>";
echo "<p><strong>Code HTTP :</strong> " . $http_code . "</p>";

if ($response === false) {
    echo "<p><strong>Erreur cURL :</strong>" . curl_error($ch) . "</p>";
} else {
    echo "<pre>" . $response . "</pre>";
    
    // Essayer de décoder la réponse JSON
    $decoded_response = json_decode($response, true);
    if ($decoded_response !== null) {
        echo "<h3>Réponse décodée :</h3>";
        echo "<pre>" . print_r($decoded_response, true) . "</pre>";
    }
}

curl_close($ch);

// Test de récupération des escales
echo "<hr><h3>Test de récupération des escales :</h3>";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:3309/gestion_res/api/escales.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

$get_response = curl_exec($ch2);
$get_http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);

echo "<p><strong>Code HTTP :</strong> " . $get_http_code . "</p>";
if ($get_response !== false) {
    echo "<pre>" . $get_response . "</pre>";
}

curl_close($ch2);
?>