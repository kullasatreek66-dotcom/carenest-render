<?php
date_default_timezone_set("Asia/Bangkok");

$accessToken = getenv("LINE_CHANNEL_ACCESS_TOKEN");

$input = file_get_contents("php://input");

$data = json_decode($input, true);

if (!isset($data["events"][0]["replyToken"])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$replyToken = $data["events"][0]["replyToken"];

$replyData = [
    "replyToken" => $replyToken,
    "messages" => [
        [
            "type" => "text",
            "text" => "✅ CareNest Bot บน Render ตอบกลับได้แล้ว"
        ]
    ]
];

$options = [
    "http" => [
        "method" => "POST",
        "header" =>
            "Content-Type: application/json\r\n" .
            "Authorization: Bearer " . $accessToken . "\r\n",
        "content" => json_encode($replyData, JSON_UNESCAPED_UNICODE),
        "ignore_errors" => true
    ]
];

file_get_contents(
    "https://api.line.me/v2/bot/message/reply",
    false,
    stream_context_create($options)
);

http_response_code(200);
echo "OK";
?>
