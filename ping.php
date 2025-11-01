<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: OPTIONS, GET");
header("Content-type: application/json");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


require_once 'src/AmoCrmClient.php';
require_once 'src/functions.php';
try {
    $amoV4Client = new AmoCrmClient(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
    $startTime = microtime(true);
    $response = $amoClient->APIGet('leads');
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $rounded = round($duration, 2);

    $googleSheets = new googleSheets(SHEET_ID);
    // Обновляем данные
    $googleSheets->updateValues(SHEET_NAME . '!E2', [$rounded]);
    $googleSheets->updateValues(SHEET_NAME . '!F2', [date('d.m.Y H:i:s')]);
} catch (Exception $ex) {
    http_response_code($ex->getCode());
    echo json_encode([
        'message' => $ex->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    Write('main_errors', 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки:' . $ex->getCode());
}
