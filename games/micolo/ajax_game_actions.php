<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Невідома дія.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'end_game') {
        if (!isset($_SESSION['game_started']) || $_SESSION['game_started'] !== true) {
            $response['message'] = 'Гра не була активна.';
            echo json_encode($response);
            exit;
        }

        $played_question_ids_json = $_POST['played_question_ids'] ?? '[]';
        $played_question_ids = json_decode($played_question_ids_json, true);
        $game_over_message = $_POST['game_over_message'] ?? 'Гра завершена гравцем.';

        if (is_array($played_question_ids)) {
            $log_file = 'data/played_questions_log.json';
            $current_log = json_decode(@file_get_contents($log_file), true) ?: [];

            foreach ($played_question_ids as $qid) {
                if (!empty($qid)) { 
                    $current_log[$qid] = ($current_log[$qid] ?? 0) + 1;
                }
            }
            @file_put_contents($log_file, json_encode($current_log, JSON_PRETTY_PRINT));
            
            $_SESSION['game_over'] = true;
            $_SESSION['game_over_message'] = htmlspecialchars($game_over_message);

            if (isset($_SESSION['game_config'])) {
                $_SESSION['game_config_at_end'] = $_SESSION['game_config'];
            }
            if (isset($_SESSION['initial_player_names'])) {
                $_SESSION['initial_player_names_at_end'] = $_SESSION['initial_player_names'];
            }
            
            unset($_SESSION['game_started']);
            unset($_SESSION['current_player_index']);
            unset($_SESSION['current_round']);
            unset($_SESSION['players']);
            unset($_SESSION['initial_js_question_pool']);
            unset($_SESSION['all_questions_data_map']);

            $response = ['success' => true, 'message' => 'Гра завершена, історію записано.'];
        } else {
            $response['message'] = 'Неправильний формат ID зіграних питань.';
        }
    }
}

echo json_encode($response);
