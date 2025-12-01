<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Post Scraper - Gestor</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Instagram Post Scraper</h1>
            <p>Ingresa URLs de Instagram para obtener métricas y enviarlas a Google Sheets</p>
        </header>

        <div class="upload-section">
            <form action="process-urls.php" method="POST" id="uploadForm">
                <div class="form-group">
                    <label for="urlsInput">Ingresa las URLs de Instagram:</label>
                    <textarea
                        name="urls"
                        id="urlsInput"
                        rows="10"
                        placeholder="https://www.instagram.com/p/ABC123...&#10;https://www.instagram.com/reel/XYZ789...&#10;&#10;Pega aquí las URLs de Instagram (posts o reels), una por línea"
                        required
                    ></textarea>
                    <small>Pega las URLs de Instagram - posts o reels (una por línea)</small>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-primary">
                        <span id="btnText">Procesar URLs</span>
                        <span id="btnLoader" class="loader" style="display: none;"></span>
                    </button>
                </div>
            </form>
        </div>

        <div id="results" class="results-section" style="display: none;">
            <h2>Resultados</h2>
            <div id="resultsTable"></div>
        </div>

        <div id="error" class="error-section" style="display: none;">
            <p class="error-message"></p>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
