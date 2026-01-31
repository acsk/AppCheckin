#!/usr/bin/env bash
set -euo pipefail

# Gera o arquivo OpenAPI JSON a partir das anotações
# Estratégia:
# 1) Tenta usar PHP local
# 2) Tenta executar dentro do container Docker existente (appcheckin_php)
# 3) Fallback: usa imagem php:8.2-cli

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

OPENAPI_BIN="vendor/bin/openapi"
OUTPUT="public/swagger/openapi.json"
BASE="vendor/autoload.php"
SCAN_DIRS="app routes"
CONTAINER_NAME="appcheckin_php"

log() {
  echo "[openapi] $*"
}

run_local_php() {
  if command -v php >/dev/null 2>&1; then
    log "Usando PHP local para gerar OpenAPI"
    php "$OPENAPI_BIN" -b "$BASE" -o "$OUTPUT" $SCAN_DIRS
    return 0
  fi
  return 1
}

run_docker_exec() {
  local container="${1:-$CONTAINER_NAME}"
  if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
    log "Usando container Docker '${container}' para gerar OpenAPI"
    docker exec "$container" bash -lc "php $OPENAPI_BIN -b $BASE -o $OUTPUT $SCAN_DIRS"
    return 0
  fi
  return 1
}

run_docker_run() {
  if command -v docker >/dev/null 2>&1; then
    log "Usando imagem docker php:8.2-cli para gerar OpenAPI"
    docker run --rm -v "$ROOT_DIR":/app -w /app php:8.2-cli bash -lc "php $OPENAPI_BIN -b $BASE -o $OUTPUT $SCAN_DIRS"
    return 0
  fi
  return 1
}

# Execução
if run_local_php; then
  log "Geração concluída via PHP local"
elif run_docker_exec "$CONTAINER_NAME"; then
  log "Geração concluída via Docker exec"
elif run_docker_run; then
  log "Geração concluída via Docker run"
else
  log "Falha ao gerar OpenAPI: PHP/Docker não disponíveis"
  exit 1
fi

if [ -f "$OUTPUT" ]; then
  log "Arquivo gerado com sucesso: $OUTPUT"
else
  log "Falha: arquivo não encontrado em $OUTPUT"
  exit 1
fi
