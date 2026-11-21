<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/assistant.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function assistant_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    assistant_json_response(['error'=>'POST is required.'],405);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    assistant_json_response(['error'=>'The request is too large.'],413);
}
$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    assistant_json_response(['error'=>'Send a valid JSON request.'],400);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    assistant_json_response(['error'=>'Your session expired. Refresh the page and try again.'],419);
}
$action = $input['action'] ?? 'message';
if (!in_array($action, ['message','history','clear'], true)) {
    assistant_json_response(['error'=>'Unsupported assistant action.'],400);
}
$user = current_user();
$pdo = db();
try {
    $conversationId = assistant_conversation_id($pdo,$user);
    if ($action === 'history') {
        assistant_json_response(['messages'=>assistant_history($pdo,$conversationId)]);
    }
    if ($action === 'clear') {
        $pdo->prepare('DELETE FROM assistant_messages WHERE conversation_id=?')->execute([$conversationId]);
        unset($_SESSION['assistant_interaction_id'], $_SESSION['assistant_interaction_model']);
        assistant_json_response(['messages'=>[]]);
    }
    $message = $input['message'] ?? null;
    if (!is_string($message)) {
        assistant_json_response(['error'=>'Enter a text message.'],400);
    }
    $message = trim($message);
    if ($message === '' || mb_strlen($message) > 1000) {
        assistant_json_response(['error'=>'Messages must contain between 1 and 1,000 characters.'],422);
    }
    $now = time();
    $recent = array_filter($_SESSION['assistant_requests'] ?? [], static fn(int $timestamp): bool => $timestamp > $now - 60);
    if (count($recent) >= 10) {
        assistant_json_response(['error'=>'You are sending messages quickly. Please wait a moment and try again.'],429);
    }
    $recent[] = $now;
    $_SESSION['assistant_requests'] = array_values($recent);
    assistant_save_message($pdo,$conversationId,'user',$message);
    try {
        $reply = assistant_reply_for_message($pdo,$message,$user,$conversationId);
    } catch (AssistantProviderException $error) {
        error_log('Giftly Gemini provider error: '.$error->getMessage());
        assistant_json_response(['error'=>'Gemini could not answer right now. Please try again later.'],502);
    }
    assistant_save_message($pdo,$conversationId,'assistant',$reply['text']);
    assistant_json_response(['reply'=>$reply]);
} catch (Throwable $error) {
    error_log('Giftly assistant error: '.$error->getMessage());
    assistant_json_response(['error'=>'The assistant is temporarily unavailable. Please try again later.'],500);
}
