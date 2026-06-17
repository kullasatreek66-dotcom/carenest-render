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
| ข้อมูลจากฐานข้อมูล CareNest ตามรูป phpMyAdmin ที่ส่งมา
|--------------------------------------------------------------------------
*/

if (mb_strpos($userText, "วัคซีน") !== false) {

    $replyText =
        "💉 Vaccine Summary\n\n" .
        "ข้อมูลวัคซีนจากฐานข้อมูล CareNest\n\n" .
        "👶 น้อง Toy / child_id 2\n" .
        "ได้รับวัคซีนแล้ว 2 รายการ\n" .
        "1) BCG\n" .
        "วันที่รับ: 08/05/2019\n\n" .
        "2) MMR\n" .
        "วันที่รับ: 30/12/2019\n\n" .
        "👶 น้องของขวัญ / child_id 6\n" .
        "ได้รับวัคซีนแล้ว 1 รายการ\n" .
        "1) DTP-HB-Hib 3\n" .
        "วันที่รับ: 29/04/2026\n\n" .
        "📌 ข้อมูลนี้อ้างอิงจากตาราง vaccine_records";

} elseif (mb_strpos($userText, "โภชนาการ") !== false || mb_strpos($userText, "เจริญเติบโต") !== false) {

    $replyText =
        "🥗 Nutrition / Growth Summary\n\n" .
        "ข้อมูลการเจริญเติบโตจากฐานข้อมูล CareNest\n\n" .
        "👶 น้อง Toy / child_id 2\n\n" .
        "รายการล่าสุดตามวันที่บันทึก:\n" .
        "วันที่: 11/12/2025\n" .
        "น้ำหนัก: 10.00 กก.\n" .
        "ส่วนสูง: 87.00 ซม.\n" .
        "BMI โดยประมาณ: 13.2\n\n" .
        "ประวัติที่มีในระบบ:\n" .
        "• 12/06/2024: 8.00 กก., 67.00 ซม.\n" .
        "• 11/06/2025: 12.00 กก., 85.00 ซม.\n" .
        "• 11/12/2025: 10.00 กก., 87.00 ซม.\n\n" .
        "📌 ข้อมูลนี้อ้างอิงจากตาราง growth_records";

} elseif (mb_strpos($userText, "พัฒนาการ") !== false) {

    $replyText =
        "🧸 Development Summary\n\n" .
        "ข้อมูลพัฒนาการจากฐานข้อมูล CareNest\n\n" .
        "👶 น้อง Toy / child_id 2\n\n" .
        "ผลประเมินล่าสุด:\n" .
        "วันที่ประเมิน: 25/04/2026\n" .
        "หมวดพัฒนาการ: Gross Motor (GM)\n" .
        "รายการประเมิน: Jumps with both feet off the ground\n" .
        "ผลการประเมิน: Achieved\n" .
        "อายุที่ทำได้: 2\n\n" .
        "รายการพัฒนาการที่พบในระบบ เช่น\n" .
        "• การเข้าใจภาษา (RL): ชี้อวัยวะได้ 7 ส่วน\n" .
        "• การช่วยเหลือตัวเองและสังคม (PS): ล้างและเช็ดมือได้เอง\n" .
        "• Fine Motor (FM): Uses simple tools to solve tasks independently\n" .
        "• Gross Motor (GM): Jumps with both feet off the ground\n\n" .
        "📌 ข้อมูลนี้อ้างอิงจากตาราง development_records";

} else {

    $replyText =
        "สวัสดีค่ะ 🌿\n" .
        "CareNest Bot พร้อมให้บริการ\n\n" .
        "พิมพ์คำที่ต้องการดูข้อมูลได้เลย:\n" .
        "• วัคซีน\n" .
        "• โภชนาการ\n" .
        "• พัฒนาการ";
}

/*
|--------------------------------------------------------------------------
| ส่งข้อความกลับ LINE
|--------------------------------------------------------------------------
*/

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
