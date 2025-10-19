<?php
require_once __DIR__ . '/../conn.php';
$baseURL = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? "https://" : "http://";
$baseURL .= $_SERVER['HTTP_HOST'];

$stmt = $pdo->prepare("
    SELECT 
        room.room_number,
        room.room_qr_ID,
        qr_code.qr_image_path
    FROM 
        room
    JOIN 
        qr_code ON room.room_qr_ID = qr_code.qr_ID
    WHERE 
        room.room_status = 'active';");
$stmt->execute();
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Barcode List</title>
  <link rel="stylesheet" href="/ims/bootstrap/css/bootstrap.min.css">
  <style>
body {
  font-family: Arial, sans-serif;
  padding: 20px;
}

.main-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  width: 50%;
  margin: auto;
}

.print-button {
  margin-bottom: 20px;
  display: flex;
  justify-content: flex-end;
}

.btn-form-green {
  background: linear-gradient(to bottom right, #005a34, #006705, #009708) !important;
  color: white !important;
  transition: all 0.1s ease-in-out;
}
.btn-form-green:hover {
  background: linear-gradient(to bottom right, #009708, #006705, #005a34) !important;
  color: white !important;
}

.asset-card {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 90px;
  border-bottom: 1px solid #ccc;
  padding: 20px 0;
  page-break-inside: avoid;
}

.barcode-box {
  width: 140px;
  text-align: center;
}

.barcode-box img:first-child {
  height: 30px;
  object-fit: contain;
  margin-bottom: 10px;
}

.barcode-box img:last-child {
  height: 100px;
  width: 100px;
  object-fit: contain;
}

.details-box {
  flex: 1;
  font-size: 14px;
}

.details-box p {
  margin: 4px 0;
  line-height: 1.5;
}

.details-box strong {
  font-weight: 600;
}

h2 {
  font-weight: bold;
  text-align: center;
  margin-bottom: 30px;
}

@media print {
  .print-button {
    display: none;
  }

  body {
    margin: 0;
    padding: 0;
  }

  .main-container {
    width: 100% !important; 
    padding: 0 20px;         
  }

  .asset-card {
    page-break-inside: avoid;
    padding: 10px 0;
    gap: 100px;       
  }

  .barcode-box img:first-child {
    height: 30px;
  }

  .barcode-box img:last-child {
    height: 80px;
    width: 80px;
  }
}

  </style>
</head>
<body>
  <div class="print-button">
      <button onclick="window.print()" class="btn btn-form-green">Download / Print PDF</button>
    </div>
  <div class="main-container">
    <h2>Room QR-code List</h2>

    <?php foreach ($rooms as $room): ?>
      <div class="asset-card">
        <div class="barcode-box">
          <img src="<?= $baseURL ?>/IMS-REACT/frontend/public/<?= $room['qr_image_path'] ?>" alt="QR Code">
        </div>
        <div class="details-box">
          <p>
            <strong>Room Number:</strong> <?= $room['room_number'] ?>
          </p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</body>

</html>
