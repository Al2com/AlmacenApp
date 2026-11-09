-- Se ejecuta solo al inicializar el volumen de datos por primera vez.
-- Crea la base de datos de test y da permisos al usuario de la aplicación
-- sobre ella (además de la de producción que crea MySQL automáticamente).
CREATE DATABASE IF NOT EXISTS almacen_test;
GRANT ALL PRIVILEGES ON almacen_test.* TO 'almacen'@'%';
FLUSH PRIVILEGES;
