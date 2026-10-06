#!/bin/bash

# Script para ejecutar las pruebas unitarias y mostrar resultados

echo "=================================================="
echo "    SISTEMA DE PRUEBAS B2B - ANIMALSCENTER"
echo "=================================================="
echo

# Verificar si el sistema Docker está funcionando
if ! docker-compose ps > /dev/null 2>&1; then
  echo "ERROR: No se puede conectar con Docker. Verifique que Docker está en ejecución."
  exit 1
fi

# Verificar si los contenedores están en ejecución
RUNNING_CONTAINERS=$(docker-compose ps --services --filter "status=running" | wc -l)
if [ "$RUNNING_CONTAINERS" -lt 2 ]; then
  echo "Los contenedores no están en ejecución. Iniciando servicios..."
  docker-compose up -d
  
  # Esperar a que los servicios estén listos
  echo "Esperando a que los servicios estén listos..."
  sleep 10
fi

# Ejecutar las pruebas dentro del contenedor
echo "Ejecutando pruebas unitarias..."
docker-compose exec app php tests/run-tests.php

# Verificar salida
EXIT_CODE=$?
if [ $EXIT_CODE -ne 0 ]; then
  echo
  echo "ALERTA: Algunas pruebas han fallado."
  echo "Intente revisar los logs para más información:"
  echo "  docker-compose logs app"
else
  echo
  echo "¡Todas las pruebas se han ejecutado correctamente!"
fi

echo
echo "=================================================="
echo "    PRUEBAS FINALIZADAS"
echo "=================================================="
