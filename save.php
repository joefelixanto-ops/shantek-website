<?php
// ═══════════════════════════════════════════════
//  save.php — Shantek Instruments Data API
//  Upload this file to your GoDaddy public_html
// ═══════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$dataFile = 'data.json';

// ── GET: Return current data ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($dataFile)) {
        echo file_get_contents($dataFile);
    } else {
        // Return default data if no file exists yet
        echo json_encode(getDefaultData());
    }
    exit;
}

// ── POST: Save new data ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check password
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($body['password']) || $body['password'] !== 'shantek123') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Remove password before saving
    unset($body['password']);
    $body['lastUpdated'] = date('Y-m-d H:i:s');

    // Save to data.json
    $result = file_put_contents($dataFile, json_encode($body, JSON_PRETTY_PRINT));
    
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Data saved successfully!', 'time' => $body['lastUpdated']]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save. Check file permissions.']);
    }
    exit;
}

// ── Default Data ──
function getDefaultData() {
    return [
        'company' => [
            'name'     => 'Shantek Instruments Pvt. Ltd.',
            'tagline'  => 'Precision Weighing Solutions',
            'phone'    => '+91 22 1234 5678',
            'whatsapp' => '912212345678',
            'email'    => 'info@shantekinstruments.com',
            'address'  => 'Andheri (E), Mumbai — 400069',
            'about'    => 'India\'s trusted manufacturer of precision weighing instruments since 1995. ISO 9001:2015 certified.',
        ],
        'products' => [
            ['id'=>1,'name'=>'ST-PL Heavy Duty Platform Scale','cat'=>'Platform Scale','price'=>'₹8,500','badge'=>'Best Seller','desc'=>'Industrial mild-steel build for warehousing and logistics.','spec1'=>'150 kg','spec2'=>'±5g','spec3'=>'RS-232','emoji'=>'⚖️','img'=>'','bg'=>'linear-gradient(135deg,#e8f0fe,#c7d9ff)'],
            ['id'=>2,'name'=>'ST-AB 220g Analytical Balance','cat'=>'Analytical Balance','price'=>'₹32,000','badge'=>'New','desc'=>'Four-decimal precision for pharma and research labs.','spec1'=>'220g','spec2'=>'0.1mg','spec3'=>'GLP/GMP','emoji'=>'🔬','img'=>'','bg'=>'linear-gradient(135deg,#e0f7ea,#b8eecb)'],
            ['id'=>3,'name'=>'ST-BMI Body Weight Scale','cat'=>'Medical Scale','price'=>'₹14,500','badge'=>'Medical','desc'=>'Clinically approved for hospitals and diagnostics.','spec1'=>'300 kg','spec2'=>'BMI Calc','spec3'=>'BIS','emoji'=>'💊','img'=>'','bg'=>'linear-gradient(135deg,#fef3e2,#fde1b0)'],
            ['id'=>4,'name'=>'ST-JW Gold & Jewellery Scale','cat'=>'Jewellery Scale','price'=>'₹5,200','badge'=>'Premium','desc'=>'Touchscreen with tare function for jewellery shops.','spec1'=>'500g','spec2'=>'0.01g','spec3'=>'Touch','emoji'=>'💎','img'=>'','bg'=>'linear-gradient(135deg,#f3e8ff,#ddb6ff)'],
            ['id'=>5,'name'=>'ST-CR Wireless Crane Scale','cat'=>'Crane Scale','price'=>'₹45,000','badge'=>'Heavy Duty','desc'=>'100m wireless remote display for steel mills.','spec1'=>'5000 kg','spec2'=>'100m','spec3'=>'IP65','emoji'=>'🏗️','img'=>'','bg'=>'linear-gradient(135deg,#e0f0ff,#b3d4ff)'],
            ['id'=>6,'name'=>'ST-MA Halogen Moisture Analyser','cat'=>'Moisture Analyser','price'=>'₹68,000','badge'=>'Featured','desc'=>'Fast halogen heating for food, plastics and chemicals.','spec1'=>'120g','spec2'=>'0.01%','spec3'=>'USB','emoji'=>'🌡️','img'=>'','bg'=>'linear-gradient(135deg,#fde8e8,#ffbdbd)'],
        ],
        'slides' => [
            ['num'=>1,'title'=>'Industrial Platform Scales Built to Last','subtitle'=>'ISO 9001:2015 Certified','desc'=>'Heavy-duty weighing platforms for logistics and manufacturing.','badge'=>'±5g','badgeLabel'=>'Accuracy','btn1'=>'Explore Products','btn2'=>'Request Quote','bg'=>'','emoji'=>'⚖️'],
            ['num'=>2,'title'=>'Analytical Balances for Precision Labs','subtitle'=>'Pharmaceutical Grade','desc'=>'Sub-milligram accuracy for pharma and research applications.','badge'=>'0.1mg','badgeLabel'=>'Resolution','btn1'=>'View Balances','btn2'=>'Get NABL Cert','bg'=>'','emoji'=>'🔬'],
            ['num'=>3,'title'=>'Crane & Hook Scales Up to 5000 kg','subtitle'=>'Heavy Industrial','desc'=>'Wireless remote display, IP65 weatherproof. Trusted across India.','badge'=>'5T','badgeLabel'=>'Max Capacity','btn1'=>'View Crane Scales','btn2'=>'Custom Specs','bg'=>'','emoji'=>'🏗️'],
        ],
        'categories' => [
            ['emoji'=>'⚖️','name'=>'Platform Scales','count'=>'128 products'],
            ['emoji'=>'🔬','name'=>'Analytical Balances','count'=>'64 products'],
            ['emoji'=>'🏥','name'=>'Medical Scales','count'=>'42 products'],
            ['emoji'=>'💎','name'=>'Jewellery Scales','count'=>'38 products'],
            ['emoji'=>'🏗️','name'=>'Crane Scales','count'=>'22 products'],
            ['emoji'=>'🌡️','name'=>'Moisture Analysers','count'=>'18 products'],
        ],
        'testimonials' => [
            ['name'=>'Rajesh Kulkarni','role'=>'Operations Head, Mahindra Logistics','text'=>'Platform scales running 24/7 for 3 years with zero calibration drift. Outstanding quality.','stars'=>5],
            ['name'=>'Sunita Patil','role'=>'QA Director, Sun Pharmaceutical','text'=>'Standardised on Shantek balances across all 12 QC labs. NABL support is unmatched.','stars'=>5],
            ['name'=>'Arjun Mehta','role'=>'Founder, Mehta Gold & Jewels','text'=>'Competitive pricing with zero compromise on precision. 8 years of reliability.','stars'=>4],
        ],
        'stats' => [
            ['num'=>'30+','label'=>'Years'],
            ['num'=>'500+','label'=>'Products'],
            ['num'=>'10K+','label'=>'Clients'],
            ['num'=>'28','label'=>'States'],
            ['num'=>'99.2%','label'=>'Satisfaction'],
        ],
        'lastUpdated' => date('Y-m-d H:i:s'),
    ];
}
?>
<?php
// ================================================
// save_demo.php
// Save demo request form data to MySQL
// Place this file in: htdocs/shantek/save_demo.php
// ================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ---- DB CONFIG (XAMPP defaults) ----
$host = 'localhost';
$db   = 'shantek_db';   // ← change to your DB name
$user = 'root';         // ← XAMPP default
$pass = '';             // ← XAMPP default is empty

// ---- CONNECT ----
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'msg' => 'DB connection failed']);
    exit;
}

// ---- GET POST DATA ----
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$name    = $conn->real_escape_string(trim($data['name']    ?? ''));
$phone   = $conn->real_escape_string(trim($data['phone']   ?? ''));
$email   = $conn->real_escape_string(trim($data['email']   ?? ''));
$company = $conn->real_escape_string(trim($data['company'] ?? ''));
$message = $conn->real_escape_string(trim($data['message'] ?? ''));

// ---- VALIDATE ----
if (!$name || !$phone || !$email) {
    echo json_encode(['status' => 'error', 'msg' => 'Required fields missing']);
    exit;
}

// ---- INSERT ----
$sql = "INSERT INTO demo_requests (name, phone, email, company, message, submitted_at)
        VALUES ('$name', '$phone', '$email', '$company', '$message', NOW())";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success', 'msg' => 'Request saved!']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Insert failed: ' . $conn->error]);
}

$conn->close();
?>
