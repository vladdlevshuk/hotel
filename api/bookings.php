<?php
namespace database;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once (__DIR__.'/../database/dbmanager.php');

$dbManager = new DataBaseManager();

$requestMethod = $_SERVER['REQUEST_METHOD'];
$method = isset($_GET['method']) ? $_GET['method'] : '';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

switch ($requestMethod) {
    case 'GET':
        if (isset($_GET['room_id'])) {
            $roomId = $_GET['room_id'];
            $bookings = $dbManager->get_bookings_for_room($roomId);
            echo json_encode($bookings);
        } else {
            $bookings = $dbManager->get_all_data('bookings');
            echo json_encode($bookings);
        }
        break;

    case 'POST':
        switch ($method) {
            case 'insert':
                if (isset($data['room_id'], $data['guest_name'], $data['guest_email'], $data['start_date'], $data['end_date'])) {
                    $roomPrice = $dbManager->get_room_price($data['room_id']);
                    if ($roomPrice === null) {
                        echo json_encode(['error' => 'Комната не найдена']);
                        break;
                    }

                    $startDate = new \DateTime($data['start_date']);
                    $endDate = new \DateTime($data['end_date']);

                    if ($startDate > $endDate) {
                        echo json_encode(['error' => 'Дата заезда не может быть позже даты выезда']);
                        break;
                    }

                    $interval = $startDate->diff($endDate);
                    $numberOfDays = $interval->days + 1;

                    $totalPrice = $roomPrice * $numberOfDays;

                    $bookingData = [
                        'room_id' => $data['room_id'],
                        'guest_name' => $data['guest_name'],
                        'guest_email' => $data['guest_email'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'total_price' => $totalPrice
                    ];
                    $result = $dbManager->insert_data('bookings', $bookingData);
                    echo json_encode(['result' => $result]);
                } else {
                    echo json_encode(['error' => 'Не все данные указаны']);
                }
                break;

            case 'update':
                if (isset($data['id'])) {
                    $updatedFields = [
                        'room_id' => $data['room_id'] ?? null,
                        'guest_name' => $data['guest_name'] ?? null,
                        'guest_email' => $data['guest_email'] ?? null,
                        'start_date' => $data['start_date'] ?? null,
                        'end_date' => $data['end_date'] ?? null,
                        'is_confirmed' => $data['is_confirmed'] ?? null,
                    ];

                    // Remove null fields (optional, for cleaner update)
                    $updatedFields = array_filter($updatedFields, function ($value) {
                        return $value !== null;
                    });

                    if (empty($updatedFields)) {
                        echo json_encode(['error' => 'Нет полей для обновления']);
                        break;
                    }

                    $result = $dbManager->update_data('bookings', $updatedFields, $data['id']);
                    echo json_encode(['result' => $result]);
                } else {
                    echo json_encode(['error' => 'ID бронирования не указан']);
                }
                break;

            case 'delete':
                if (isset($data['id'])) {
                    $result = $dbManager->delete_data('bookings', $data['id']);
                    echo json_encode(['result' => $result]);
                } else {
                    echo json_encode(['error' => 'ID бронирования не указан']);
                }
                break;

            default:
                echo json_encode(['error' => 'Неверный метод']);
                break;
        }
        break;

    default:
        echo json_encode(['error' => 'Метод не поддерживается']);
        break;
}
