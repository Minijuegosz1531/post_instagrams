<?php

class FTPHelper {
    private $connection;
    private $host;
    private $username;
    private $password;

    public function __construct($host, $username, $password) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Conectar al servidor FTP
     */
    public function connect() {
        $this->connection = ftp_connect($this->host);

        if (!$this->connection) {
            throw new Exception("No se pudo conectar al servidor FTP: {$this->host}");
        }

        $login = ftp_login($this->connection, $this->username, $this->password);

        if (!$login) {
            throw new Exception("Error de autenticación FTP");
        }

        // Activar modo pasivo
        ftp_pasv($this->connection, true);

        return true;
    }

    /**
     * Subir archivo al FTP
     *
     * @param string $localFile Ruta local del archivo
     * @param string $remoteFile Nombre del archivo en el FTP
     * @return bool
     */
    public function uploadFile($localFile, $remoteFile) {
        if (!$this->connection) {
            throw new Exception("No hay conexión FTP activa");
        }

        if (!file_exists($localFile)) {
            throw new Exception("El archivo local no existe: {$localFile}");
        }

        $upload = ftp_put($this->connection, $remoteFile, $localFile, FTP_BINARY);

        if (!$upload) {
            throw new Exception("Error al subir archivo al FTP: {$remoteFile}");
        }

        return true;
    }

    /**
     * Descargar imagen desde URL y subirla al FTP
     *
     * @param string $imageUrl URL de la imagen a descargar
     * @param string $remoteFileName Nombre del archivo en el FTP
     * @return string URL pública de la imagen en el FTP
     */
    public function uploadImageFromUrl($imageUrl, $remoteFileName) {
        // Crear directorio temporal si no existe
        $tempDir = __DIR__ . '/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Descargar imagen temporalmente
        $tempFile = $tempDir . '/' . $remoteFileName;

        $imageContent = @file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new Exception("No se pudo descargar la imagen desde: {$imageUrl}");
        }

        file_put_contents($tempFile, $imageContent);

        // Subir al FTP en el directorio posts/
        $remotePath = $remoteFileName;
        $this->uploadFile($tempFile, $remotePath);

        // Eliminar archivo temporal
        unlink($tempFile);

        // Retornar URL pública
        return "https://{$this->host}/posts/{$remoteFileName}";
    }

    /**
     * Cerrar conexión FTP
     */
    public function close() {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }

    /**
     * Destructor para cerrar conexión automáticamente
     */
    public function __destruct() {
        $this->close();
    }
}
