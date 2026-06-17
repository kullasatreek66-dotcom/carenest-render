<?php
date_default_timezone_set("Asia/Bangkok");

$accessToken = getenv("LINE_CHANNEL_ACCESS_TOKEN");

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data["events"][0])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$event = $data["events"][0];

if (!isset($event["replyToken"])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$replyToken = $event["replyToken"];

$userText = "";
if (isset($event["message"]["text"])) {
    $userText = trim($event["message"]["text"]);
}

/*
|--------------------------------------------------------------------------
| ข้อมูลจริงจากหน้าเว็บ CareNest
|--------------------------------------------------------------------------
*/
$childNickname = "Toy";
$childFullName = "Tinnaporn Vej-aporn";
$developmentAge = "31 เดือน";

if (mb_strpos($userText, "วัคซีน") !== false) {

    $replyText =
        "💉 Vaccine Summary\n\n" .
        "👶 เด็ก: " . $childNickname . "\n" .
        "ชื่อเต็ม: " . $childFullName . "\n\n" .
        "ได้รับวัคซีนแล้ว 2 รายการ\n\n" .
        "วัคซีนล่าสุด: MMR\n" .
        "วันที่รับ: 30/12/2019\n\n" .
        "รายการวัคซีนที่บันทึกไว้:\n" .
        "1. MMR — 30 Dec 2019\n" .
        "2. BCG — 08 May 2019\n\n" .
        "📅 วัคซีนถัดไป: HB 2\n" .
        "อายุที่ควรได้รับ: 1 เดือน\n\n" .
        "อ้างอิงจากข้อมูลในระบบ CareNest";

} elseif (mb_strpos($userText, "โภชนาการ") !== false) {

    $replyText =
        "🍽️ Nutrition Summary\n\n" .
        "👶 เด็ก: " . $childNickname . "\n" .
        "ชื่อเต็ม: " . $childFullName . "\n\n" .
        "ช่วงอายุ: 2–5 Years\n" .
        "คำแนะนำ: 3 main meals, 5 food groups, limit sugar/salt.\n\n" .
        "ปริมาณอาหารแนะนำ:\n" .
        "• Rice/Grains: 4 ladles\n" .
        "• Meat/Fish: 3–4 tbsp\n" .
        "• Vegetables: 1.5 ladles\n" .
        "• Milk: 2 glasses\n\n" .
        "ตัวอย่างเมนูอาหาร:\n" .
        "• Grilled Salmon Rice\n" .
        "• Pancake & Fresh Fruit Set\n" .
        "• Colorful Mixed Fried Rice\n" .
        "• Rice Noodle Chicken Soup\n\n" .
        "อ้างอิงจากข้อมูลในระบบ CareNest";

} elseif (mb_strpos($userText, "พัฒนาการ") !== false) {

    $replyText =
        "🧸 Development Summary\n\n" .
        "👶 เด็ก: " . $childNickname . "\n" .
        "ชื่อเต็ม: " . $childFullName . "\n" .
        "Milestones: " . $developmentAge . "\n\n" .
        "รายการพัฒนาการที่แสดงในระบบ:\n" .
        "• Gross Motor (GM): Jumps with both feet off the ground\n" .
        "• Fine Motor (FM): Uses simple tools to solve tasks independently\n" .
        "• Receptive Language (RL): Identifies 7 different body parts correctly\n" .
        "• Expressive Language (EL): Responds with appropriate \"Yes\" or \"No\"\n" .
        "• Personal & Social (PS): Able to wash and dry hands by themselves\n\n" .
        "หมายเหตุ: จากหน้าระบบยังไม่ได้บันทึกผลผ่าน/ไม่ผ่าน และยังไม่มี personal notes\n\n" .
        "อ้างอิงจากข้อมูลในระบบ CareNest";

} else {

    $replyText =
        "สวัสดีค่ะ 🌿\n" .
        "CareNest Bot พร้อมให้บริการ\n\n" .
        "พิมพ์คำที่ต้องการดูข้อมูลได้เลย:\n" .
        "• วัคซีน\n" .
        "• โภชนาการ\n" .
        "• พัฒนาการ";
}

$replyData = [
    "replyToken" => $replyToken,
    "messages" => [
        [
            "type" => "text",
            "text" => $replyText
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
