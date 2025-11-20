console.log('🚀 Script main.js cargado');

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM cargado completamente');

    const form = document.getElementById('uploadForm');
    const resultsDiv = document.getElementById('results');
    const resultsTable = document.getElementById('resultsTable');
    const errorDiv = document.getElementById('error');
    const btnText = document.getElementById('btnText');
    const btnLoader = document.getElementById('btnLoader');

    console.log('📋 Elementos principales cargados:', {
        form: !!form,
        resultsDiv: !!resultsDiv,
        resultsTable: !!resultsTable,
        errorDiv: !!errorDiv
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Ocultar mensajes previos
        resultsDiv.style.display = 'none';
        errorDiv.style.display = 'none';

        // Mostrar loader
        btnText.style.display = 'none';
        btnLoader.style.display = 'inline-block';

        const formData = new FormData(form);

        try {
            const response = await fetch('process.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            // Ocultar loader
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';

            if (data.error) {
                showError(data.error);
            } else {
                showResults(data.data, data.message);
            }
        } catch (error) {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            showError('Error al procesar la solicitud: ' + error.message);
        }
    });

    function showResults(data, message) {
        if (!data || data.length === 0) {
            showError('No se obtuvieron resultados');
            return;
        }

        let tableHTML = '';

        // Agregar mensaje de éxito si existe
        if (message) {
            tableHTML += `<div class="success-message">${message}</div>`;
        }

        tableHTML += `
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>URL</th>
                        <th>Usuario</th>
                        <th>Caption</th>
                        <th>Comentarios</th>
                        <th>Vistas</th>
                        <th>Reproducciones</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(row => {
            const caption = row.caption.length > 100
                ? row.caption.substring(0, 100) + '...'
                : row.caption;

            tableHTML += `
                <tr>
                    <td>${row.fecha}</td>
                    <td><a href="${row.inputUrl}" target="_blank">Ver post</a></td>
                    <td>@${row.ownerUsername}</td>
                    <td title="${escapeHtml(row.caption)}">${escapeHtml(caption)}</td>
                    <td>${formatNumber(row.commentsCount)}</td>
                    <td>${formatNumber(row.videoViewCount)}</td>
                    <td>${formatNumber(row.videoPlayCount)}</td>
                </tr>
            `;
        });

        tableHTML += `
                </tbody>
            </table>
        `;

        resultsTable.innerHTML = tableHTML;
        resultsDiv.style.display = 'block';

        // Scroll suave hacia los resultados
        resultsDiv.scrollIntoView({ behavior: 'smooth' });
    }

    function showError(message) {
        errorDiv.querySelector('.error-message').textContent = message;
        errorDiv.style.display = 'block';
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('es-CO').format(num);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
