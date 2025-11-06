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
require_once 'src/constants.php';
require_once 'src/googleSheetsClient.php';
require_once 'vendor/autoload.php';

try {
    // Подключение к AmoCRM
    $amoV4Client = new AmoCrmClient(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
    $users = $amoV4Client->GETAll('users'); // Получаем Пользователей
    write('users', $users); // Здесь и дальше это логгирование

    $start_time = time() - 86400; // За последние сутки
    $end_time = time();
    // Получаем события
    $events = $amoV4Client->GETAll('events', [
        'created_at[from]' => $start_time,
        'created_at[to]' => $end_time
    ]);
    write('events', $events);

    // Создаем массив ID пользователей
    $user_ids = [];
    foreach ($users as $user) {
        $user_ids[$user['id']] = true; // Тут любое значение
    }
    // Инициализация счетчиков для каждого типа
    $counts = [
        'lead_added' => 0,
        'lead_updated' => 0,
        'lead_deleted' => 0,
        'lead_responsible_changed' => 0,
        'lead_status_changed' => 0,
        'lead_pipeline_changed' => 0,
        'contact_added' => 0,
        'contact_updated' => 0,
        'contact_deleted' => 0,
        'contact_responsible_changed' => 0,
        'company_added' => 0,
        'company_updated' => 0,
        'company_deleted' => 0,
        'company_responsible_changed' => 0,
        'note_added' => 0,
        'note_updated' => 0,
        'note_deleted' => 0,
        'task_created' => 0,
        'task_updated' => 0,
        'task_deleted' => 0,
        'email_sent' => 0,
    ];
    // Перебираем события
    foreach ($events as $event) {
        $created_by = $event['created_by'];
        if (isset($user_ids[$created_by])) {
            continue; // Событие создано пользователем
        } else {
            if (isset($event['type'])) {
                $type = $event['type'];
                // Проверяем, есть ли такой тип в списке
                if (array_key_exists($type, $counts)) {
                    $counts[$type]++; // Увеличиваем счетчик по типу
                }
            }
        }
    }
    // Сортировка по убыванию
    arsort($counts);
    // Выборка первых 5
    $topFive = array_slice($counts, 0, 5);
    write('topFive', $topFive);

    // Переводим на русский
    $ruNames = [];
    $i = 0;
    foreach ($topFive as $key => $value) {
        // Получаем описание события из константы EVENTS
        $eventDescription = (string)EVENTS[$key];
        $ruNames[] = [$eventDescription, (int)$value];
    }

    $googleSheets = new googleSheetsClient(SHEET_ID);
    $oldData = $googleSheets->getValues(SHEET_NAME . '!A2:C6');
    write('oldData', $oldData);

    // Форматируем данные для Google
    $formattedData = [];
    foreach ($oldData as $key => $data) {
        $formattedData[] = [(string)$data[1], (int)$data[2]];
        if ($key == 0) {
            $googleSheets->updateValues(SHEET_NAME . '!A10', [[$data[0]]]);
        }
    }
    write('formattedData', $formattedData);
    write('ruNames', $ruNames);
    //Добовляем дату
    $googleSheets->updateValues(SHEET_NAME . '!A2', [[date('d.m.Y')]]);
    // Старые в конец
    $googleSheets->updateValues(SHEET_NAME . '!B10:C14', $formattedData);
    // Обновляем данные
    $googleSheets->updateValues(SHEET_NAME . '!B2:C6', $ruNames);
} catch (Exception $ex) {
    http_response_code($ex->getCode());
    echo json_encode([
        'message' => $ex->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    Write('main_errors', 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки:' . $ex->getCode());
}
