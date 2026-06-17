<?php
date_default_timezone_set("Asia/Bangkok");
error_reporting(E_ALL);
ini_set("display_errors", 0);

/* =========================
   Environment Variables
========================= */
$accessToken = getenv("LINE_CHANNEL_ACCESS_TOKEN");

$dbHost = getenv("DB_HOST");
$dbName = getenv("DB_NAME");
$dbUser = getenv("DB_USER");
$dbPass = getenv("DB_PASS");
$dbPort = getenv("DB_PORT") ?: 3306;

/* =========================
   Helper: log สำหรับดูปัญหาใน Render
========================= */
function writeLog($text) {
    file_put_contents(
        __DIR__ . "/debug_log.txt",
        date("Y-m-d H:i:s") . " | " . $text . PHP_EOL,
        FILE_APPEND
    );
}

/* =========================
   Helper: ส่งข้อความกลับ LINE
   ใช้ file_get_contents แทน curl
========================= */
function replyLine($replyToken, $replyText, $accessToken) {
    if (!$replyToken || !$accessToken) {
        writeLog("Missing replyToken or accessToken");
        return;
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

    $response = file_get_contents(
        "https://api.line.me/v2/bot/message/reply",
        false,
        stream_context_create($options)
    );

    writeLog("LINE Response: " . $response);
}

/* =========================
   Helper: format วันที่
========================= */
function formatDateThai($date) {
    if (!$date || $date === "0000-00-00") {
        return "-";
    }

    $time = strtotime($date);
    if (!$time) {
        return $date;
    }

    return date("d/m/Y", $time);
}

/* =========================
   Helper: เลือกค่าจากหลายชื่อคอลัมน์
========================= */
function pick($row, $keys, $default = "-") {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== "" && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

/* =========================
   รับข้อมูลจาก LINE
========================= */
$input = file_get_contents("php://input");
writeLog("RAW INPUT: " . $input);

$data = json_decode($input, true);

if (!isset($data["events"][0])) {
    http_response_code(200);
    echo "OK";
    exit;
}

$event = $data["events"][0];

if (($event["type"] ?? "") !== "message") {
    http_response_code(200);
    echo "OK";
    exit;
}

if (($event["message"]["type"] ?? "") !== "text") {
    http_response_code(200);
    echo "OK";
    exit;
}

$replyToken = $event["replyToken"] ?? "";
$messageText = trim($event["message"]["text"] ?? "");
$lineUserId = $event["source"]["userId"] ?? "";

writeLog("MESSAGE: " . $messageText);
writeLog("LINE USER ID: " . $lineUserId);

/* =========================
   เช็ก Environment
========================= */
if (!$accessToken || !$dbHost || !$dbName || !$dbUser || !$dbPass) {
    replyLine(
        $replyToken,
        "❌ ยังตั้งค่า Environment Variables บน Render ไม่ครบ",
        $accessToken
    );

    http_response_code(200);
    echo "OK";
    exit;
}

/* =========================
   เชื่อมฐานข้อมูล
========================= */
$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, (int)$dbPort);

if ($conn->connect_error) {
    writeLog("DB CONNECT ERROR: " . $conn->connect_error);

    replyLine(
        $replyToken,
        "❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้\n\nError: " . $conn->connect_error,
        $accessToken
    );

    http_response_code(200);
    echo "OK";
    exit;
}

$conn->set_charset("utf8mb4");

/* =========================
   หา user จาก line_user_id
   ถ้าไม่พบ ใช้ user_id แรกเพื่อเดโมก่อน
========================= */
$user = null;

if ($lineUserId !== "") {
    $stmt = $conn->prepare("
        SELECT user_id, fullname
        FROM users
        WHERE line_user_id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $lineUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$user) {
    $result = $conn->query("
        SELECT user_id, fullname
        FROM users
        ORDER BY user_id ASC
        LIMIT 1
    ");

    $user = $result ? $result->fetch_assoc() : null;
}

if (!$user) {
    replyLine(
        $replyToken,
        "❌ ไม่พบข้อมูลผู้ใช้ในฐานข้อมูล",
        $accessToken
    );

    http_response_code(200);
    echo "OK";
    exit;
}

/* =========================
   หาเด็กคนแรกของ user
========================= */
$stmt = $conn->prepare("
    SELECT child_id, child_name, nickname, birthdate
    FROM children
    WHERE user_id = ?
    ORDER BY child_id ASC
    LIMIT 1
");

if (!$stmt) {
    replyLine(
        $replyToken,
        "❌ ไม่สามารถอ่านข้อมูลเด็กได้\n\nError: " . $conn->error,
        $accessToken
    );

    http_response_code(200);
    echo "OK";
    exit;
}

$stmt->bind_param("i", $user["user_id"]);
$stmt->execute();
$child = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$child) {
    replyLine(
        $replyToken,
        "❌ ยังไม่มีข้อมูลเด็กในระบบ",
        $accessToken
    );

    http_response_code(200);
    echo "OK";
    exit;
}

$childId = $child["child_id"];
$childName = $child["nickname"] ?: $child["child_name"];
$childFullName = $child["child_name"];

/* =========================
   คำสั่ง: วัคซีน
========================= */
if (mb_strpos($messageText, "วัคซีน") !== false) {

    $stmt = $conn->prepare("
        SELECT vaccine_name, date_administered, notes
        FROM vaccine_records
        WHERE child_id = ?
        ORDER BY date_administered DESC
        LIMIT 1
    ");

    if (!$stmt) {
        replyLine(
            $replyToken,
            "❌ อ่านข้อมูลวัคซีนไม่ได้\n\nError: " . $conn->error,
            $accessToken
        );

        http_response_code(200);
        echo "OK";
        exit;
    }

    $stmt->bind_param("i", $childId);
    $stmt->execute();
    $latest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM vaccine_records
        WHERE child_id = ?
    ");

    $total = 0;

    if ($stmt) {
        $stmt->bind_param("i", $childId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = $count["total"] ?? 0;
    }

    if ($latest) {
        $replyText =
            "💉 Vaccine Summary\n\n" .
            "👶 เด็ก: " . $childName . "\n" .
            "ชื่อเต็ม: " . $childFullName . "\n\n" .
            "ได้รับวัคซีนแล้ว " . $total . " รายการ\n\n" .
            "วัคซีนล่าสุด: " . $latest["vaccine_name"] . "\n" .
            "วันที่รับ: " . formatDateThai($latest["date_administered"]) . "\n" .
            "หมายเหตุ: " . ($latest["notes"] ?: "-") . "\n\n" .
            "ข้อมูลนี้ดึงจากฐานข้อมูล CareNest";
    } else {
        $replyText =
            "💉 Vaccine Summary\n\n" .
            "👶 เด็ก: " . $childName . "\n\n" .
            "ยังไม่มีข้อมูลวัคซีนในฐานข้อมูล";
    }

    replyLine($replyToken, $replyText, $accessToken);

    http_response_code(200);
    echo "OK";
    exit;
}

/* =========================
   คำสั่ง: โภชนาการ
========================= */
if (mb_strpos($messageText, "โภชนาการ") !== false) {

    $stmt = $conn->prepare("
        SELECT *
        FROM growth_records
        WHERE child_id = ?
        ORDER BY growth_id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        replyLine(
            $replyToken,
            "❌ อ่านข้อมูลโภชนาการไม่ได้\n\nError: " . $conn->error,
            $accessToken
        );

        http_response_code(200);
        echo "OK";
        exit;
    }

    $stmt->bind_param("i", $childId);
    $stmt->execute();
    $growth = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($growth) {
        $weight = pick($growth, ["weight", "weight_kg"]);
        $height = pick($growth, ["height", "height_cm"]);
        $bmi = pick($growth, ["bmi"]);
        $status = pick($growth, ["status", "nutrition_status"]);
        $recordDate = pick($growth, ["record_date", "created_at"]);

        if (($bmi === "-" || $bmi === "") && is_numeric($weight) && is_numeric($height) && $height > 0) {
            $bmi = number_format($weight / pow($height / 100, 2), 2);
        }

        $replyText =
            "🥗 Nutrition Summary\n\n" .
            "👶 เด็ก: " . $childName . "\n" .
            "ชื่อเต็ม: " . $childFullName . "\n\n" .
            "น้ำหนักล่าสุด: " . $weight . " กก.\n" .
            "ส่วนสูงล่าสุด: " . $height . " ซม.\n" .
            "BMI: " . $bmi . "\n" .
            "สถานะโภชนาการ: " . $status . "\n" .
            "วันที่บันทึก: " . formatDateThai($recordDate) . "\n\n" .
            "ข้อมูลนี้ดึงจากฐานข้อมูล CareNest";
    } else {
        $replyText =
            "🥗 Nutrition Summary\n\n" .
            "👶 เด็ก: " . $childName . "\n\n" .
            "ยังไม่มีข้อมูลโภชนาการในฐานข้อมูล";
    }

    replyLine($replyToken, $replyText, $accessToken);

    http_response_code(200);
    echo "OK";
    exit;
}

/* =========================
   คำสั่ง: พัฒนาการ
========================= */
if (mb_strpos($messageText, "พัฒนาการ") !== false) {

    $stmt = $conn->prepare("
        SELECT *
        FROM development_records
        WHERE child_id = ?
        ORDER BY dev_id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        replyLine(
            $replyToken,
            "❌ อ่านข้อมูลพัฒนาการไม่ได้\n\nError: " . $conn->error,
            $accessToken
        );

        http_response_code(200);
        echo "OK";
        exit;
    }

    $stmt->bind_param("i", $childId);
    $stmt->execute();
    $dev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM development_records
        WHERE child_id = ?
    ");

    $total = 0;

    if ($stmt) {
        $stmt->bind_param("i", $childId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = $count["total"] ?? 0;
    }

    if ($dev) {
        $age = pick($dev, ["age_months", "month_age", "milestone_age"]);
        $result = pick($dev, ["result", "status", "development_status"]);
        $note = pick($dev, ["notes", "note", "personal_notes"]);
        $recordDate = pick($dev, ["record_date", "created_at"]);

        $replyText =
            "🧸 Development Summary\n\n" .
            "👶 เด็ก: " . $childName . "\n" .
            "ชื่อเต็ม: " . $childFullName . "\n\n" .
            "มีบันทึกพัฒนาการ " . $total . " รายการ\n" .
            "ช่วงอายุล่าสุด: " . $age . "\n" .
            "ผลล่าสุด: " . $result . "\n" .
            "หมายเหตุ: " . $note . "\n" .
            "วันที่บันทึก: " . formatDateThai($recordDate) . "\n\n" .
            "ข้อมูลนี้ดึงจากฐานข้อมูล CareNest";
    } else {
        $replyText =
            "🧸 Development Summary\n\n" .
            "👶 เด็ก: " . $childName . "\n\n" .
            "ยังไม่มีข้อมูลพัฒนาการในฐานข้อมูล";
    }

    replyLine($replyToken, $replyText, $accessToken);

    http_response_code(200);
    echo "OK";
    exit;
}

/* =========================
   Default Reply
========================= */
$replyText =
    "สวัสดีค่ะ 🌿\n" .
    "CareNest Bot พร้อมให้บริการ\n\n" .
    "พิมพ์คำที่ต้องการดูข้อมูลได้เลย:\n" .
    "• วัคซีน\n" .
    "• โภชนาการ\n" .
    "• พัฒนาการ";

replyLine($replyToken, $replyText, $accessToken);

http_response_code(200);
echo "OK";
?>
