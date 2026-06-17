<?php
date_default_timezone_set("Asia/Bangkok");
error_reporting(E_ALL);
ini_set('display_errors', 0);

/*
|--------------------------------------------------------------------------
| ดึงค่าจาก Environment Variables บน Render
|--------------------------------------------------------------------------
*/
$accessToken = getenv("LINE_CHANNEL_ACCESS_TOKEN");
$apiUrl = getenv("CARE_NEST_API_URL");
$apiSecret = getenv("CARE_NEST_API_SECRET");

/*
|--------------------------------------------------------------------------
| รับข้อมูลจาก LINE
|--------------------------------------------------------------------------
*/
$input = file_get_contents("php://input");

file_put_contents(
    __DIR__ . "/line_log.txt",
    $input . PHP_EOL,
    FILE_APPEND
);

$data = json_decode($input, true);

if (!isset($data["events"][0])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$event = $data["events"][0];

if ($event["type"] !== "message" || !isset($event["message"]["text"])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$messageText = trim($event["message"]["text"]);
$replyToken = $event["replyToken"];
$lineUserId = $event["source"]["userId"] ?? "";

/*
|--------------------------------------------------------------------------
| ฟังก์ชันส่งข้อความกลับ LINE
|--------------------------------------------------------------------------
*/
function replyLine($replyToken, $replyText, $accessToken) {
    $replyData = [
        "replyToken" => $replyToken,
        "messages" => [
            [
                "type" => "text",
                "text" => $replyText
            ]
        ]
    ];

    $ch = curl_init("https://api.line.me/v2/bot/message/reply");

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($replyData, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $accessToken
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);

    file_put_contents(
        __DIR__ . "/reply_log.txt",
        date("Y-m-d H:i:s") . PHP_EOL .
        "Response: " . $response . PHP_EOL .
        "Curl Error: " . $curlError . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    curl_close($ch);
}

/*
|--------------------------------------------------------------------------
| ฟังก์ชันเรียก API ของ CareNest ที่ InfinityFree
|--------------------------------------------------------------------------
*/
function fetchCareNestReply($apiUrl, $apiSecret, $lineUserId, $command) {
    $postFields = http_build_query([
        "secret" => $apiSecret,
        "line_user_id" => $lineUserId,
        "command" => $command
    ]);

    $ch = curl_init($apiUrl);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    file_put_contents(
        __DIR__ . "/api_call_log.txt",
        date("Y-m-d H:i:s") . PHP_EOL .
        "Command: " . $command . PHP_EOL .
        "LINE User ID: " . $lineUserId . PHP_EOL .
        "Response: " . $response . PHP_EOL .
        "Curl Error: " . $curlError . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    if ($curlError) {
        return "❌ ไม่สามารถเชื่อมต่อฐานข้อมูล CareNest ได้";
    }

    $json = json_decode($response, true);

    if (!$json || !isset($json["reply_text"])) {
        return "❌ ระบบไม่สามารถประมวลผลข้อมูลได้";
    }

    return $json["reply_text"];
}

/*
|--------------------------------------------------------------------------
| ตรวจว่า environment variables ครบไหม
|--------------------------------------------------------------------------
*/
if (!$accessToken || !$apiUrl || !$apiSecret) {
    replyLine($replyToken, "❌ ยังไม่ได้ตั้งค่า environment variables บน Render", $accessToken);
    http_response_code(200);
    echo "OK";
    exit;
}

/*
|--------------------------------------------------------------------------
| ดึงข้อความจากฐานข้อมูลผ่าน API
|--------------------------------------------------------------------------
*/
$replyText = fetchCareNestReply($apiUrl, $apiSecret, $lineUserId, $messageText);

/*
|--------------------------------------------------------------------------
| ส่งข้อความกลับ LINE
|--------------------------------------------------------------------------
*/
replyLine($replyToken, $replyText, $accessToken);

http_response_code(200);
echo "OK";
?>
