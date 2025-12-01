<?php
/**
 * Procesa URLs de Instagram enviadas desde el formulario
 * Ejecuta la misma lógica que cron-process.php pero desde la interfaz web
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/GoogleSheetsHelper.php';
require_once __DIR__ . '/FTPHelper.php';

header('Content-Type: application/json');

// Verificar que se enviaron URLs
if (!isset($_POST['urls']) || empty(trim($_POST['urls']))) {
    echo json_encode(['error' => 'No se enviaron URLs']);
    exit;
}

// Extraer URLs del textarea (una por línea)
$inputUrls = explode("\n", $_POST['urls']);
$urls = [];

foreach ($inputUrls as $url) {
    $url = trim($url);
    if (empty($url)) {
        continue;
    }

    // Validar que sea una URL de Instagram (post o reel)
    if (preg_match('/instagram\.com\/(p|reel)\/[A-Za-z0-9_-]+/', $url)) {
        $urls[] = $url;
    }
}

if (empty($urls)) {
    echo json_encode(['error' => 'No se encontraron URLs válidas de Instagram']);
    exit;
}

// Inicializar Google Sheets Helper
try {
    $sheetsHelper = new GoogleSheetsHelper(GOOGLE_CREDENTIALS_PATH);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al conectar con Google Sheets: ' . $e->getMessage()]);
    exit;
}

// Preparar payload para Apify
$apifyPayload = [
    'resultsLimit' => 1,
    'skipPinnedPosts' => false,
    'username' => $urls
];

// Llamar a Apify
$apifyResponse = callApifyActor($apifyPayload);

if (!$apifyResponse || isset($apifyResponse['error'])) {
    echo json_encode(['error' => 'Error al obtener datos de Apify: ' . ($apifyResponse['error'] ?? 'Desconocido')]);
    exit;
}

// Procesar resultados y extraer campos necesarios
$processedData = [];
$updatedRows = [];
$currentDate = date('Y-m-d');

// Conectar al FTP
try {
    $ftpHelper = new FTPHelper(FTP_HOST, FTP_USER, FTP_PASSWORD);
    $ftpHelper->connect();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al conectar con FTP: ' . $e->getMessage()]);
    exit;
}

foreach ($apifyResponse as $index => $item) {
    $inputUrl = $item['inputUrl'] ?? '';

    if (empty($inputUrl)) {
        continue;
    }

    // Verificar si la URL ya existe en Google Sheets
    $existingRow = $sheetsHelper->findUrlRow(GOOGLE_SHEET_ID, $inputUrl);

    $imageUrl = '';
    $shouldDownloadImage = true;

    if ($existingRow !== null) {
        // Reutilizar imagen existente (sin importar la fecha)
        $imageUrl = $existingRow['imageUrl'];
        $shouldDownloadImage = false;

        $isSameDay = $sheetsHelper->isSameDay($existingRow['fecha']);

        if ($isSameDay) {
            // Es del mismo día - actualizar la fila existente

            // Preparar datos actualizados
            $row = [
                'fecha' => $currentDate,
                'inputUrl' => $inputUrl,
                'caption' => $item['caption'] ?? '',
                'ownerUsername' => $item['ownerUsername'] ?? '',
                'commentsCount' => $item['commentsCount'] ?? 0,
                'videoViewCount' => $item['videoViewCount'] ?? 0,
                'videoPlayCount' => $item['videoPlayCount'] ?? 0,
                'imageUrl' => $imageUrl,
                'timestamp' => $item['timestamp'] ?? ''
            ];

            // Actualizar la fila existente
            $sheetsHelper->updateRow(GOOGLE_SHEET_ID, $existingRow['rowIndex'], $row);
            $updatedRows[] = $row;
            continue;

        }
    }

    // Descargar y subir imagen si es necesario
    if ($shouldDownloadImage && !empty($item['displayUrl'])) {
        try {
            // Generar nombre único para la imagen
            $shortCode = $item['shortCode'] ?? uniqid();
            $extension = 'jpg';
            $fileName = $shortCode . '_' . time() . '.' . $extension;

            // Subir imagen al FTP
            $imageUrl = $ftpHelper->uploadImageFromUrl($item['displayUrl'], $fileName);
        } catch (Exception $e) {
            // Si falla la subida de imagen, continuar sin ella
            error_log('Error al subir imagen: ' . $e->getMessage());
        }
    }

    // Agregar fila nueva
    $row = [
        'fecha' => $currentDate,
        'inputUrl' => $inputUrl,
        'caption' => $item['caption'] ?? '',
        'ownerUsername' => $item['ownerUsername'] ?? '',
        'commentsCount' => $item['commentsCount'] ?? 0,
        'videoViewCount' => $item['videoViewCount'] ?? 0,
        'videoPlayCount' => $item['videoPlayCount'] ?? 0,
        'imageUrl' => $imageUrl,
        'timestamp' => $item['timestamp'] ?? ''
    ];
    $processedData[] = $row;
}

// Enviar nuevas filas a Google Sheets
try {
    if (!empty($processedData)) {
        $result = $sheetsHelper->appendData(GOOGLE_SHEET_ID, $processedData);
    }

    $message = [];
    if (!empty($processedData)) {
        $message[] = count($processedData) . ' filas nuevas agregadas';
    }
    if (!empty($updatedRows)) {
        $message[] = count($updatedRows) . ' filas actualizadas';
    }

    echo json_encode([
        'success' => true,
        'data' => array_merge($processedData, $updatedRows),
        'message' => implode(', ', $message)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'data' => $processedData,
        'error' => 'Error al enviar a Google Sheets: ' . $e->getMessage()
    ]);
}

/**
 * Llamar al actor de Apify
 */
function callApifyActor($payload) {
    $ch = curl_init();

    $url = 'https://api.apify.com/v2/acts/apify~instagram-post-scraper/runs?token=' . APIFY_API_TOKEN;

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 300 // 5 minutos timeout
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }

    curl_close($ch);

    if ($httpCode !== 201 && $httpCode !== 200) {
        return ['error' => 'HTTP Error: ' . $httpCode];
    }

    $runData = json_decode($response, true);

    if (!isset($runData['data']['defaultDatasetId'])) {
        return ['error' => 'No se pudo iniciar el actor de Apify'];
    }

    // Esperar a que termine el actor y obtener resultados
    $runId = $runData['data']['id'];
    return waitForApifyResults($runId);
}

/**
 * Esperar resultados del actor de Apify
 */
function waitForApifyResults($runId) {
    $maxAttempts = 60; // Máximo 5 minutos (60 * 5 segundos)
    $attempt = 0;

    while ($attempt < $maxAttempts) {
        sleep(5); // Esperar 5 segundos entre intentos

        $ch = curl_init();
        $url = 'https://api.apify.com/v2/actor-runs/' . $runId . '?token=' . APIFY_API_TOKEN;

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $runStatus = json_decode($response, true);

        if (isset($runStatus['data']['status'])) {
            if ($runStatus['data']['status'] === 'SUCCEEDED') {
                // Obtener resultados del dataset
                return getApifyDataset($runStatus['data']['defaultDatasetId']);
            } elseif ($runStatus['data']['status'] === 'FAILED' || $runStatus['data']['status'] === 'ABORTED') {
                return ['error' => 'El actor de Apify falló o fue abortado'];
            }
        }

        $attempt++;
    }

    return ['error' => 'Timeout esperando resultados de Apify'];
}

/**
 * Obtener datos del dataset de Apify
 */
function getApifyDataset($datasetId) {
    $ch = curl_init();
    $url = 'https://api.apify.com/v2/datasets/' . $datasetId . '/items?token=' . APIFY_API_TOKEN;

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['error' => 'Error obteniendo dataset: HTTP ' . $httpCode];
    }

    return json_decode($response, true);
}
