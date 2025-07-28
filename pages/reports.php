<?php
require_once '../includes/auth_guard.php';

// Guardia de Rol: Solo Administradores pueden acceder.
if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// Valores por defecto para el filtro de fechas
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$page_title = 'Reportes y Estadísticas';
include_once '../templates/header.php';
?>

<h2><?php echo htmlspecialchars($page_title); ?></h2>
<p>Análisis de datos del sistema de citas. Use los filtros para definir un periodo de tiempo.</p>

<div class="filter-form">
    <form method="GET" action="reports.php">
        <div>
            <label for="start_date">Fecha de Inicio:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div>
            <label for="end_date">Fecha de Fin:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <div>
            <button type="submit" class="btn">Filtrar</button>
        </div>
    </form>
</div>

<div class="reports-container">
    <div class="report-card">
        <h3>Citas por Estado</h3>
        <canvas id="statusChart"></canvas>
    </div>
    <div class="report-card">
        <h3>Top 5 Especialidades Solicitadas</h3>
        <canvas id="specialtiesChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Obtenemos las fechas del formulario para pasarlas a la API
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    // URL de nuestro endpoint de datos
    const apiUrl = `api_report_data.php?start_date=${startDate}&end_date=${endDate}`;

    // Gráficos (inicializados vacíos)
    let statusChart = null;
    let specialtiesChart = null;

    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            // --- Renderizar Gráfico de Estados ---
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            statusChart = new Chart(statusCtx, {
                type: 'pie', // Tipo de gráfico
                data: {
                    labels: data.statusReport.labels,
                    datasets: [{
                        label: 'Citas',
                        data: data.statusReport.data,
                        backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#007bff', '#6c757d', '#17a2b8'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Distribución de Citas por Estado' }
                    }
                }
            });

            // --- Renderizar Gráfico de Especialidades ---
            const specialtiesCtx = document.getElementById('specialtiesChart').getContext('2d');
            specialtiesChart = new Chart(specialtiesCtx, {
                type: 'bar', // Tipo de gráfico
                data: {
                    labels: data.specialtiesReport.labels,
                    datasets: [{
                        label: 'Número de Citas',
                        data: data.specialtiesReport.data,
                        backgroundColor: 'rgba(0, 86, 160, 0.7)',
                        borderColor: 'rgba(0, 86, 160, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y', // Barras horizontales para mejor lectura
                    scales: { x: { beginAtZero: true } },
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Especialidades más Solicitadas' }
                    }
                }
            });
        })
        .catch(error => console.error('Error al cargar los datos para los gráficos:', error));
});
</script>


<style>
    .filter-form form { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-end; background-color: #f9f9f9; padding: 1.5rem; border-radius: var(--border-radius); margin-bottom: 2rem; }
    .filter-form form div { display: flex; flex-direction: column; }
    .reports-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
    .report-card { background-color: var(--white); border: 1px solid var(--light-gray); border-radius: var(--border-radius); padding: 1.5rem; box-shadow: var(--box-shadow); }
    .report-card h3 { margin-top: 0; color: var(--primary-color); text-align: center; }
</style>

<?php
include_once '../templates/footer.php';
?>