#!/usr/bin/env php
<?php
/**
 * Script para ejecutar desde cronjob
 * Lee URLs desde Google Sheets (hoja "Urls") y las procesa automáticamente
 *
 * Uso: php cron-process.php
 * O desde cron: cada 5 minutos - ver CRONJOB-SETUP.md para ejemplos
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/GoogleSheetsHelper.php';
require_once __DIR__ . '/FTPHelper.php';

// Función para logging
function log_message($message) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

log_message('🚀 Iniciando proceso automático de scraping...');

// Inicializar Google Sheets Helper
try {
    $sheetsHelper = new GoogleSheetsHelper(GOOGLE_CREDENTIALS_PATH);
    log_message('✅ Conexión con Google Sheets establecida');
} catch (Exception $e) {
    log_message('❌ Error al conectar con Google Sheets: ' . $e->getMessage());
    exit(1);
}

// Leer URLs desde Google Sheets
try {
    $urlsRange = GOOGLE_SHEET_READ_URL . '!A:A';
    $urlsData = $sheetsHelper->getData(GOOGLE_SHEET_ID, $urlsRange);

    if (empty($urlsData)) {
        log_message('⚠️  No se encontraron URLs en la hoja "' . GOOGLE_SHEET_READ_URL . '"');
        exit(0);
    }

    // Extraer URLs (saltar header si existe)
    $urls = [];
    foreach ($urlsData as $index => $row) {
        if ($index === 0 && isset($row[0]) && strtolower($row[0]) === 'url') {
            continue; // Saltar header
        }

        if (!empty($row[0])) {
            $url = trim($row[0]);
            // Validar que sea una URL de Instagram
            if (preg_match('/instagram\.com\/p\/[A-Za-z0-9_-]+/', $url)) {
                $urls[] = $url;
            }
        }
    }

    log_message('📋 URLs encontradas: ' . count($urls));

    if (empty($urls)) {
        log_message('⚠️  No se encontraron URLs válidas de Instagram');
        exit(0);
    }

} catch (Exception $e) {
    log_message('❌ Error al leer URLs: ' . $e->getMessage());
    exit(1);
}

// Preparar payload para Apify
$apifyPayload = [
    'resultsLimit' => 1,
    'skipPinnedPosts' => false,
    'username' => $urls
];

log_message('🔄 Llamando a Apify con ' . count($urls) . ' URLs...');

// Llamar a Apify
$apifyResponse = callApifyActor($apifyPayload);

if (!$apifyResponse || isset($apifyResponse['error'])) {
    log_message('❌ Error al obtener datos de Apify: ' . ($apifyResponse['error'] ?? 'Desconocido'));
    exit(1);
}

log_message('✅ Datos recibidos de Apify: ' . count($apifyResponse) . ' posts');

// Procesar resultados y extraer campos necesarios
$processedData = [];
$updatedRows = [];
$currentDate = date('Y-m-d H:i:s');

// Conectar al FTP
try {
    $ftpHelper = new FTPHelper(FTP_HOST, FTP_USER, FTP_PASSWORD);
    $ftpHelper->connect();
    log_message('✅ Conexión FTP establecida');
} catch (Exception $e) {
    log_message('❌ Error al conectar con FTP: ' . $e->getMessage());
    exit(1);
}

foreach ($apifyResponse as $index => $item) {
    $inputUrl = $item['inputUrl'] ?? '';

    if (empty($inputUrl)) {
        continue;
    }

    log_message('📝 Procesando: ' . $inputUrl);

    // Verificar si la URL ya existe en Google Sheets
    $existingRow = $sheetsHelper->findUrlRow(GOOGLE_SHEET_ID, $inputUrl);

    $imageUrl = '';
    $shouldDownloadImage = true;

    if ($existingRow !== null) {
        log_message('   🔍 URL encontrada en fila ' . $existingRow['rowIndex']);

        // Reutilizar imagen existente (sin importar la fecha)
        $imageUrl = $existingRow['imageUrl'];
        $shouldDownloadImage = false;
        log_message('   ♻️  Reutilizando imagen existente: ' . $imageUrl);

        if ($sheetsHelper->isSameDay($existingRow['fecha'])) {
            // Es del mismo día - actualizar la fila existente
            log_message('   🔄 Misma fecha - actualizando fila existente');

            // Preparar datos actualizados
            $row = [
                'fecha' => $currentDate,
                'inputUrl' => $inputUrl,
                'caption' => $item['caption'] ?? '',
                'ownerUsername' => $item['ownerUsername'] ?? '',
                'commentsCount' => $item['commentsCount'] ?? 0,
                'videoViewCount' => $item['videoViewCount'] ?? 0,
                'videoPlayCount' => $item['videoPlayCount'] ?? 0,
                'imageUrl' => $imageUrl
            ];

            // Actualizar la fila existente
            $sheetsHelper->updateRow(GOOGLE_SHEET_ID, $existingRow['rowIndex'], $row);
            $updatedRows[] = $row;
            log_message('   ✅ Fila actualizada');
            continue;

        } else {
            // Es de otro día - agregar nueva fila pero con la misma imagen
            log_message('   📅 Fecha diferente - creando nueva fila con imagen existente');
        }
    } else {
        log_message('   ➕ URL nueva - descargando imagen');
    }

    // Descargar y subir imagen si es necesario
    if ($shouldDownloadImage && !empty($item['displayUrl'])) {
        try {
            log_message('   📥 Descargando imagen...');
            // Generar nombre único para la imagen
            $shortCode = $item['shortCode'] ?? uniqid();
            $extension = 'jpg';
            $fileName = $shortCode . '_' . time() . '.' . $extension;

            // Subir imagen al FTP
            $imageUrl = $ftpHelper->uploadImageFromUrl($item['displayUrl'], $fileName);
            log_message('   ✅ Imagen subida: ' . $imageUrl);
        } catch (Exception $e) {
            log_message('   ⚠️  Error al subir imagen: ' . $e->getMessage());
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
        'imageUrl' => $imageUrl
    ];
    $processedData[] = $row;
    log_message('   ✅ Datos procesados');
}

// Enviar nuevas filas a Google Sheets
try {
    if (!empty($processedData)) {
        $result = $sheetsHelper->appendData(GOOGLE_SHEET_ID, $processedData);
        log_message('✅ ' . count($processedData) . ' filas nuevas agregadas a Google Sheets');
    }

    if (!empty($updatedRows)) {
        log_message('✅ ' . count($updatedRows) . ' filas actualizadas');
    }

    $totalProcessed = count($processedData) + count($updatedRows);
    log_message('🎉 Proceso completado exitosamente - Total procesado: ' . $totalProcessed);

    exit(0);

} catch (Exception $e) {
    log_message('❌ Error al enviar a Google Sheets: ' . $e->getMessage());
    exit(1);
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
        log_message('   ⏳ Esperando resultados de Apify... (' . $attempt . '/' . $maxAttempts . ')');
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
