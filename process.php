<?php
require_once 'config/config.php';
require_once 'GoogleSheetsHelper.php';
require_once 'FTPHelper.php';

header('Content-Type: application/json');

// Verificar que se subió un archivo
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se subió ningún archivo o hubo un error en la carga']);
    exit;
}

// Validar extensión
$fileName = $_FILES['csvFile']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExtension !== 'csv') {
    echo json_encode(['error' => 'El archivo debe ser un CSV']);
    exit;
}

// Mover archivo a carpeta uploads
$uploadPath = 'uploads/' . time() . '_' . basename($fileName);
if (!move_uploaded_file($_FILES['csvFile']['tmp_name'], $uploadPath)) {
    echo json_encode(['error' => 'Error al guardar el archivo']);
    exit;
}

// Leer URLs del CSV
$urls = [];
if (($handle = fopen($uploadPath, 'r')) !== false) {
    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        if (!empty($data[0])) {
            $url = trim($data[0]);
            // Validar que sea una URL de Instagram (post o reel)
            if (preg_match('/instagram\.com\/(p|reel)\/[A-Za-z0-9_-]+/', $url)) {
                $urls[] = $url;
            }
        }
    }
    fclose($handle);
}

if (empty($urls)) {
    echo json_encode(['error' => 'No se encontraron URLs válidas de Instagram en el archivo']);
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
$currentDate = date('Y-m-d H:i:s');

// Inicializar Google Sheets Helper
try {
    $sheetsHelper = new GoogleSheetsHelper(GOOGLE_CREDENTIALS_PATH);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al conectar con Google Sheets: ' . $e->getMessage()]);
    exit;
}

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
        // La URL ya existe en Google Sheets - reutilizar imagen existente
        $imageUrl = $existingRow['imageUrl'];
        $shouldDownloadImage = false;

        if ($sheetsHelper->isSameDay($existingRow['fecha'])) {
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
            continue; // No agregar a processedData, ya se actualizó directamente

        } else {
            // Es de otro día - agregar nueva fila pero con la misma imagen
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

// Limpiar archivo subido
unlink($uploadPath);

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
