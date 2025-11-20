<?php
error_log('🧪 [TEST] test-google-sheets.php iniciado');

require_once 'config/config.php';
require_once 'GoogleSheetsHelper.php';
require_once 'FTPHelper.php';

header('Content-Type: application/json');

error_log('🧪 [TEST] Headers y requires cargados');

// Leer el archivo respuesta.json
$responseFile = __DIR__ . '/respuesta.json';
error_log('🧪 [TEST] Buscando archivo: ' . $responseFile);

if (!file_exists($responseFile)) {
    error_log('❌ [TEST] ERROR: Archivo no encontrado');
    echo json_encode([
        'success' => false,
        'error' => 'No se encontró el archivo respuesta.json'
    ]);
    exit;
}

error_log('✅ [TEST] Archivo encontrado, leyendo contenido...');
$jsonContent = file_get_contents($responseFile);
$apifyResponse = json_decode($jsonContent, true);

if (!$apifyResponse || !is_array($apifyResponse)) {
    error_log('❌ [TEST] ERROR: No se pudo parsear el JSON');
    echo json_encode([
        'success' => false,
        'error' => 'Error al leer o parsear respuesta.json'
    ]);
    exit;
}

error_log('✅ [TEST] JSON parseado correctamente. Items: ' . count($apifyResponse));

// Procesar resultados y extraer campos necesarios
$processedData = [];
$updatedRows = [];
$currentDate = date('Y-m-d H:i:s');

// Inicializar Google Sheets Helper primero
try {
    $sheetsHelper = new GoogleSheetsHelper(GOOGLE_CREDENTIALS_PATH);
    error_log('✅ [TEST] GoogleSheetsHelper creado');
} catch (Exception $e) {
    error_log('❌ [TEST] Error al crear GoogleSheetsHelper: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al conectar con Google Sheets: ' . $e->getMessage()]);
    exit;
}

// Conectar al FTP
try {
    $ftpHelper = new FTPHelper(FTP_HOST, FTP_USER, FTP_PASSWORD);
    $ftpHelper->connect();
    error_log('✅ [TEST] Conexión FTP exitosa');
} catch (Exception $e) {
    error_log('❌ [TEST] Error al conectar con FTP: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al conectar con FTP: ' . $e->getMessage()]);
    exit;
}

foreach ($apifyResponse as $index => $item) {
    $inputUrl = $item['inputUrl'] ?? '';

    if (empty($inputUrl)) {
        error_log('⚠️ [TEST] URL vacía, saltando...');
        continue;
    }

    // Verificar si la URL ya existe en Google Sheets
    $existingRow = $sheetsHelper->findUrlRow(GOOGLE_SHEET_ID, $inputUrl);

    $imageUrl = '';
    $shouldDownloadImage = true;

    if ($existingRow !== null) {
        error_log('🔍 [TEST] URL encontrada en fila ' . $existingRow['rowIndex']);

        // Reutilizar imagen existente (sin importar la fecha)
        $imageUrl = $existingRow['imageUrl'];
        $shouldDownloadImage = false;
        error_log('♻️ [TEST] Reutilizando imagen existente: ' . $imageUrl);

        if ($sheetsHelper->isSameDay($existingRow['fecha'])) {
            // Es del mismo día - actualizar y reutilizar imagen
            error_log('🔄 [TEST] Misma fecha, actualizando fila');

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

            $sheetsHelper->updateRow(GOOGLE_SHEET_ID, $existingRow['rowIndex'], $row);
            $updatedRows[] = $row;
            error_log('✅ [TEST] Fila actualizada');
            continue;

        } else {
            error_log('📅 [TEST] Fecha diferente, agregando nueva fila con imagen existente');
        }
    } else {
        error_log('➕ [TEST] URL nueva, descargando imagen');
    }

    // Descargar y subir imagen si es necesario
    if ($shouldDownloadImage && !empty($item['displayUrl'])) {
        try {
            error_log('📥 [TEST] Descargando imagen ' . ($index + 1) . '...');
            $shortCode = $item['shortCode'] ?? uniqid();
            $extension = 'jpg';
            $fileName = $shortCode . '_' . time() . '_test.' . $extension;

            $imageUrl = $ftpHelper->uploadImageFromUrl($item['displayUrl'], $fileName);
            error_log('✅ [TEST] Imagen subida: ' . $imageUrl);
        } catch (Exception $e) {
            error_log('❌ [TEST] Error al subir imagen: ' . $e->getMessage());
        }
    } elseif (!$shouldDownloadImage) {
        error_log('♻️ [TEST] Reutilizando imagen existente');
    }

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
}

if (empty($processedData) && empty($updatedRows)) {
    error_log('❌ [TEST] ERROR: No hay datos procesados ni actualizados');
    echo json_encode([
        'success' => false,
        'error' => 'No se encontraron datos válidos en respuesta.json'
    ]);
    exit;
}

error_log('✅ [TEST] Datos nuevos: ' . count($processedData) . ' | Actualizados: ' . count($updatedRows));
error_log('🔄 [TEST] Intentando enviar a Google Sheets...');

// Enviar nuevas filas a Google Sheets
try {
    if (!empty($processedData)) {
        $result = $sheetsHelper->appendData(GOOGLE_SHEET_ID, $processedData);
        error_log('✅ [TEST] Datos nuevos enviados exitosamente');
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
        'message' => 'Datos de prueba procesados: ' . implode(', ', $message),
        'rowsProcessed' => count($processedData) + count($updatedRows)
    ]);
} catch (Exception $e) {
    error_log('❌ [TEST] ERROR en Google Sheets: ' . $e->getMessage());
    error_log('❌ [TEST] Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'data' => $processedData,
        'error' => 'Error al enviar a Google Sheets: ' . $e->getMessage()
    ]);
}
