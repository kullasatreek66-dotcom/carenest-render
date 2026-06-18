<?php
// 1. รับค่า Token ของคุณ (ใส่เป็นข้อความตรงๆ หรือใช้ getenv ก็ได้ค่ะ)
$accessToken = "4fY/5JLlDBx/JvHT78ON5fzgNm5SokzaLjON5X1KAoRKJqpfrZYt1UcCE3aNgufC0jdFII/a50lQVRtD03iwQx8EhJsv54VlGqNIu5AOMXWF6TUzEysbc0lcziaN+LhORx3Dyf/ZMbpzGIKxlxWq/gdB04t89/1O/w1cDnyilFU=";

// 2. รับข้อมูลจาก LINE
// แก้ไขส่วนต้นของ webhook.php บน Render เป็นแบบนี้ค่ะ
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data["events"][0])) { echo "OK"; exit; }

$event = $data["events"][0];
// เปลี่ยนการดึงค่าให้ชัดเจนขึ้น
$lineUserId = isset($event["source"]["userId"]) ? $event["source"]["userId"] : "unknown";
$userText = isset($event["message"]["text"]) ? $event["message"]["text"] : "unknown";

// 3. เตรียมข้อความตอบกลับแบบพื้นฐาน (เหมือนตอนที่บอทเคยตอบได้)
$replyText = "บอทได้รับข้อความของคุณแล้ว: " . $userText . "\nตอนนี้ระบบอยู่ในช่วงทดสอบการตอบกลับพื้นฐานค่ะ";

// 4. ส่งข้อความกลับ LINE
$replyData = [
    "replyToken" => $replyToken,
    "messages" => [["type" => "text", "text" => $replyText]]
];
// ก่อนบรรทัด curl_init("https://api.line.me/v2/bot/message/reply") 
// ให้เพิ่มคำสั่งนี้เพื่อเช็คว่า Token มาไหม
if (empty($accessToken)) {
    error_log("Error: Access Token is empty!");
    exit;
}
$ch = curl_init("https://api.line.me/v2/bot/message/reply");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($replyData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);

http_response_code(200);
echo "OK";
?>
