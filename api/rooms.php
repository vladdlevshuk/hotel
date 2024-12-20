<?php
namespace database;

use exception;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
require_once (__DIR__.'/../database/dbmanager.php');

$dbManager = new DataBaseManager();

$requestMethod = $_SERVER['REQUEST_METHOD'];
error_log("Request Method: $requestMethod");

$table_name = isset($_GET['table_name']) ? $_GET['table_name'] : '';
$method = isset($_GET['method']) ? $_GET['method'] : '';

error_log("Table Name: $table_name");
error_log("Method: $method");

$json = file_get_contents('php://input');
$data = json_decode($json, true);
error_log("Request Data: " . json_encode($data));

$table_name = 'rooms';

function validateRoomData($dbManager, $data, $excludeId = null) {
    $errors = [];

    if (empty($data['room_number'])) {
        $errors[] = 'Номер комнаты обязателен';
    } elseif (!is_numeric($data['room_number']) || $data['room_number'] <= 0) {
        $errors[] = 'Номер комнаты должен быть положительным числом';
    } elseif (strlen($data['room_number']) > 6) {
        $errors[] = 'Номер комнаты не может превышать 6 цифр';
    } else {
        $allData = $dbManager->get_all_data('rooms');
        foreach ($allData as $room) {
            if ($room['room_number'] == $data['room_number'] && $room['id'] != $excludeId) {
                $errors[] = 'Комната с таким номером уже существует';
                break;
            }
        }
    }

    if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
        $errors[] = 'Цена должна быть положительным числом с плавающей точкой';
    }

    return empty($errors) ? ['status' => 'ok'] : ['status' => 'error', 'error' => $errors];
}

switch ($requestMethod) {
    case 'GET':
        $data = $dbManager->get_all_data($table_name);

        if (isset($data['error']) && strpos($data['error'], "Table 'hotel.rooms' doesn't exist") !== false) {
            $dbManager->create_table_rooms();
        }

        $data = $dbManager->get_all_data($table_name);

        echo json_encode($data);
        break;

    case 'POST':
        switch ($method) {
            case 'create_table':
                $columns = isset($data['columns']) ? $data['columns'] : [];
                $result = $dbManager->create_table($table_name, $columns);
                echo json_encode(['result' => $result]);
                break;

            case 'insert':
                $dataToInsert = isset($data) ? $data : [];
                $validationResult = validateRoomData($dbManager, $dataToInsert);
                if ($validationResult['status'] === 'ok') {
                    try {
                        $result = $dbManager->insert_data($table_name, $dataToInsert);
                        echo json_encode(['result' => $result]);
                    } catch (Exception $e) {
                        echo json_encode(['error' => $e->getMessage()]);
                    }
                } else {
                    echo json_encode($validationResult);
                }
                break;

            case 'delete':
                $id = isset($data['id']) ? $data['id'] : null;
                if ($id === null) {
                    echo json_encode(['error' => 'ID is required for delete operation.']);
                    exit;
                }
                $result = $dbManager->delete_data($table_name, $id);
                echo json_encode(['result' => $result]);
                break;

            case 'update':
                $condition = isset($data['id']) ? $data['id'] : null;
                $newData = isset($data['new_data']) ? $data['new_data'] : null;

                $validationResult = validateRoomData($dbManager, $newData, $condition);
                if ($validationResult['status'] === 'ok') {
                    try {
                        $result = $dbManager->update_data($table_name, $newData, $condition);
                        echo json_encode(['result' => $result]);
                    } catch (Exception $e) {
                        echo json_encode(['error' => $e->getMessage()]);
                    }
                } else {
                    echo json_encode($validationResult);
                }
                break;

            default:
                echo json_encode(['error' => 'Invalid method']);
                break;
        }
        break;

    default:
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
