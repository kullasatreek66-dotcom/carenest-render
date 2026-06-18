<?php
date_default_timezone_set("Asia/Bangkok");

// 1. รับค่า Token จาก Environment Variables ของ Render
$accessToken = getenv("4fY/5JLlDBx/JvHT78ON5fzgNm5SokzaLjON5X1KAoRKJqpfrZYt1UcCE3aNgufC0jdFII/a50lQVRtD03iwQx8EhJsv54VlGqNIu5AOMXWF6TUzEysbc0lcziaN+LhORx3Dyf/ZMbpzGIKxlxWq/gdB04t89/1O/w1cDnyilFU=");

// 2. รับข้อมูลจาก LINE
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data["events"][0])) {
    echo "OK";
    exit;
}

$event = $data["events"][0];
$replyToken = $event["replyToken"];
$userText = isset($event["message"]["text"]) ? trim($event["message"]["text"]) : "";
$lineUserId = $event["source"]["userId"];

// 3. เตรียมคำสั่งเพื่อยิงไปถาม InfinityFree
// *** เปลี่ยน domain.rf.gd เป็นโดเมนจริงของคุณ ***
$api_url = "https://care-nest.rf.gd/api_line_data.php"; 

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'secret' => 'carenest_secret_2026',
    'line_user_id' => $lineUserId,
    'command' => $userText
]));

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

// 4. ได้รับคำตอบแล้วส่งกลับ LINE
$replyText = ($result && isset($result['reply_text'])) ? $result['reply_text'] : "สวัสดีค่ะ! พิมพ์ 'วัคซีน' 'โภชนาการ' หรือ 'พัฒนาการ' เพื่อดูข้อมูลนะคะ";

$replyData = [
    "replyToken" => $replyToken,
    "messages" => [["type" => "text", "text" => $replyText]]
];

$ch = curl_init("https://api.line.me/v2/bot/message/reply");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($replyData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
curl_exec($ch);
curl_close($ch);

http_response_code(200);
echo "OK";
?>
