<?php
// templates/footer.php
// Este archivo contendrá el pie de página HTML común a todas las páginas,
// incluyendo los scripts JS necesarios y el cierre de las etiquetas <body> y </html>.
// NO abre <body> ni <html>.
?>
        </div> </body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para la fecha y hora en tiempo real (copiado aquí desde el header)
        function updateRealTimeDateTime() {
            const now = new Date();
            const optionsDate = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const optionsTime = { hour: '2-digit', minute: '2-digit', hour12: true }; // 09:00 AM/PM

            const dateStr = now.toLocaleDateString('es-ES', optionsDate);
            const timeStr = now.toLocaleTimeString('en-US', optionsTime); 
            
            let formattedTime = timeStr.replace(/ /g, '').toLowerCase(); 
            formattedTime = formattedTime.replace(/:00$/, ''); 
            formattedTime = formattedTime.replace('am', 'a.m.'); 
            formattedTime = formattedTime.replace('pm', 'p.m.'); 

            const finalDateStr = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);

            document.getElementById('realTimeDateTime').textContent = `${finalDateStr} - ${formattedTime}`;
        }

        // Actualizar cada segundo
        setInterval(updateRealTimeDateTime, 1000);
        // Llamar una vez al cargar para mostrarla inmediatamente
        updateRealTimeDateTime();
    </script>
</html>