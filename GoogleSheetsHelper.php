<?php

require_once __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsHelper {
    private $service;
    private $spreadsheetId;

    public function __construct($credentialsPath) {
        if (!file_exists($credentialsPath)) {
            throw new Exception("Archivo de credenciales de Google no encontrado: {$credentialsPath}");
        }

        $client = new Client();
        $client->setApplicationName('Instagram Post Scraper');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');

        $this->service = new Sheets($client);
    }

    /**
     * Agregar datos a Google Sheets
     */
    public function appendData($spreadsheetId, $data) {
        if (empty($data)) {
            throw new Exception("No hay datos para enviar a Google Sheets");
        }

        // Preparar headers si es la primera vez
        $headers = ['Fecha', 'URL', 'Caption', 'Usuario', 'Comentarios', 'Vistas', 'Reproducciones', 'Imagen', 'Posteado en'];

        // Convertir datos a formato de array
        $values = [];

        // Verificar si ya existen headers
        $hasHeaders = $this->checkIfHasHeaders($spreadsheetId);

        if (!$hasHeaders) {
            $values[] = $headers;
        }

        // Agregar filas de datos
        foreach ($data as $row) {
            $values[] = [
                $row['fecha'],
                $row['inputUrl'],
                $row['caption'],
                $row['ownerUsername'],
                $row['commentsCount'],
                $row['videoViewCount'],
                $row['videoPlayCount'],
                $row['imageUrl'] ?? '',
                $row['timestamp'] ?? ''
            ];
        }

        $range = GOOGLE_SHEET_RANGE . '!A:I';
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $values
        ]);

        $params = [
            'valueInputOption' => 'USER_ENTERED'
        ];

        $result = $this->service->spreadsheets_values->append(
            $spreadsheetId,
            $range,
            $body,
            $params
        );

        return [
            'updatedCells' => $result->getUpdates()->getUpdatedCells(),
            'updatedRows' => $result->getUpdates()->getUpdatedRows()
        ];
    }

    /**
     * Verificar si la hoja ya tiene headers
     */
    private function checkIfHasHeaders($spreadsheetId) {
        try {
            $range = GOOGLE_SHEET_RANGE . '!A1:I1';
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            return !empty($values) && !empty($values[0]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Leer datos de la hoja
     */
    public function getData($spreadsheetId, $range) {
        $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
        return $response->getValues();
    }

    /**
     * Limpiar la hoja
     */
    public function clearSheet($spreadsheetId, $range) {
        $clear = new \Google\Service\Sheets\ClearValuesRequest();
        return $this->service->spreadsheets_values->clear($spreadsheetId, $range, $clear);
    }

    /**
     * Buscar una URL en la hoja y retornar información de la fila MÁS RECIENTE
     *
     * @param string $spreadsheetId ID de la hoja
     * @param string $url URL a buscar
     * @return array|null ['rowIndex' => número de fila, 'fecha' => fecha, 'imageUrl' => url de imagen] o null si no existe
     */
    public function findUrlRow($spreadsheetId, $url) {
        try {
            $range = GOOGLE_SHEET_RANGE . '!A:I';
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                return null;
            }

            // Buscar la URL (columna B, índice 1) - encontrar la ÚLTIMA aparición
            $lastMatch = null;

            foreach ($values as $index => $row) {
                // Saltar header (fila 0)
                if ($index === 0) continue;

                if (isset($row[1]) && $row[1] === $url) {
                    $lastMatch = [
                        'rowIndex' => $index + 1, // +1 porque las hojas empiezan en 1
                        'fecha' => $row[0] ?? '',
                        'imageUrl' => $row[7] ?? '' // Columna H (índice 7)
                    ];
                }
            }

            return $lastMatch;
        } catch (Exception $e) {
            error_log('Error al buscar URL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verificar si una fecha es del día actual
     *
     * @param string $fecha Fecha en formato Y-m-d o Y-m-d H:i:s
     * @return bool
     */
    public function isSameDay($fecha) {
        if (empty($fecha)) {
            return false;
        }

        // Limpiar espacios en blanco
        $fecha = trim($fecha);
        $today = date('Y-m-d');

        // Si la fecha ya está en formato Y-m-d, compararla directamente
        if (strlen($fecha) === 10 && strpos($fecha, ':') === false) {
            return $today === $fecha;
        }

        // Si tiene hora, extraer solo la fecha
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            return false;
        }

        $fechaDate = date('Y-m-d', $timestamp);
        return $today === $fechaDate;
    }

    /**
     * Actualizar una fila específica
     *
     * @param string $spreadsheetId ID de la hoja
     * @param int $rowIndex Número de fila (empieza en 1)
     * @param array $data Datos a actualizar
     * @return array Resultado de la actualización
     */
    public function updateRow($spreadsheetId, $rowIndex, $data) {
        $values = [[
            $data['fecha'],
            $data['inputUrl'],
            $data['caption'],
            $data['ownerUsername'],
            $data['commentsCount'],
            $data['videoViewCount'],
            $data['videoPlayCount'],
            $data['imageUrl'] ?? '',
            $data['timestamp'] ?? ''
        ]];

        $range = GOOGLE_SHEET_RANGE . '!A' . $rowIndex . ':I' . $rowIndex;
        $body = new \Google\Service\Sheets\ValueRange([
            'values' => $values
        ]);

        $params = [
            'valueInputOption' => 'USER_ENTERED'
        ];

        $result = $this->service->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            $params
        );

        return [
            'updatedCells' => $result->getUpdatedCells(),
            'updatedRows' => $result->getUpdatedRows()
        ];
    }
}
