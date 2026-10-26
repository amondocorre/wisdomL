<?php
// Permitir solicitudes CORS desde cualquier origen (desarrollo) o desde localhost:5173 / localhost:5174 / localhost:5175
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

// Responder de inmediato si la petición es de tipo preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
