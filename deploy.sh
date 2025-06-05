#!/bin/bash
set -e

if ! command -v docker-compose >/dev/null 2>&1; then
  echo "docker-compose not found. Please install Docker and docker-compose." >&2
  exit 1
fi

echo "Building containers..."
docker-compose build

echo "Starting services..."
docker-compose up -d

echo "Deployment finished. Access the app at http://localhost:8080"
