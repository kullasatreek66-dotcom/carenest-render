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

if ($userText === "วัคซีน") {

    $replyText =
        "💉 ข้อมูลวัคซีนเด็ก\n\n" .
        "ระบบ CareNest ช่วยติดตามประวัติการได้รับวัคซีน และแจ้งเตือนวัคซีนตามช่วงอายุของเด็ก\n\n" .
        "ตัวอย่างวัคซีนสำคัญ ได้แก่\n" .
        "• แรกเกิด: BCG, HB1\n" .
        "• 2 เดือน: DTP-HB-Hib, OPV, Rota\n" .
        "• 4 เดือน: DTP-HB-Hib, OPV, Rota\n" .
        "• 6 เดือน: DTP-HB-Hib, OPV\n" .
        "• 9 เดือน: MMR\n" .
        "• 1 ปี: JE\n" .
        "• 1 ปี 6 เดือน: DTP, OPV, MMR\n" .
        "• 4 ปี: DTP, OPV\n\n" .
        "📌 หมายเหตุ: ผู้ปกครองควรพาเด็กไปรับวัคซีนตามนัดของสถานพยาบาล";

} elseif ($userText === "พัฒนาการ") {

    $replyText =
        "🧸 ข้อมูลพัฒนาการเด็ก\n\n" .
        "ระบบ CareNest ช่วยติดตามพัฒนาการเด็กตามช่วงอายุ โดยอ้างอิงแนวทาง DSPM\n\n" .
        "แบ่งเป็น 4 ด้านหลัก ได้แก่\n" .
        "• กล้ามเนื้อมัดใหญ่ เช่น นั่ง คลาน ยืน เดิน\n" .
        "• กล้ามเนื้อมัดเล็กและสติปัญญา เช่น หยิบจับของ เล่นของเล่น\n" .
        "• ภาษาและการสื่อสาร เช่น หันตามเสียง เรียกพ่อแม่ พูดคำง่าย ๆ\n" .
        "• สังคมและการช่วยเหลือตนเอง เช่น ยิ้ม เล่นจ๊ะเอ๋ โบกมือลา\n\n" .
        "📌 หากพบพัฒนาการล่าช้า ควรปรึกษาเจ้าหน้าที่สาธารณสุขหรือแพทย์";

} elseif ($userText === "โภชนาการ") {

    $replyText =
        "🍽️ ข้อมูลโภชนาการเด็ก\n\n" .
        "ระบบ CareNest ช่วยติดตามน้ำหนัก ส่วนสูง และภาวะโภชนาการของเด็กตามช่วงอายุ\n\n" .
        "คำแนะนำทั่วไป:\n" .
        "• เด็กควรได้รับอาหารเหมาะสมตามวัย\n" .
        "• ควรติดตามน้ำหนักและส่วนสูงอย่างสม่ำเสมอ\n" .
        "• ส่งเสริมอาหารครบ 5 หมู่\n" .
        "• ลดอาหารหวาน มัน เค็ม\n" .
        "• เด็กเล็กควรได้รับอาหารบด/นิ่มตามช่วงวัย\n\n" .
        "📌 หากน้ำหนักน้อย น้ำหนักเกิน หรือส่วนสูงต่ำกว่าเกณฑ์ ควรปรึกษาเจ้าหน้าที่สาธารณสุข";

} else {

    $replyText =
        "สวัสดีค่ะ 🌿\n" .
        "CareNest Bot พร้อมให้บริการ\n\n" .
        "พิมพ์คำที่ต้องการดูข้อมูลได้เลย:\n" .
        "• วัคซีน\n" .
        "• พัฒนาการ\n" .
        "• โภชนาการ";
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
