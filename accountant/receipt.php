<?php
/**
 * accountant/receipt.php
 * Fee Payment Receipt — printable, professional.
 * Accountant Portal — SRS Section 7.3
 * 
 * Wraps the full receipt rendering. Auth is enforced first,
 * then the receipt data is fetched and displayed in a clean print layout.
 */
require_once __DIR__ . '/auth_check.php';

$receipt_no = trim($_GET['receipt_no'] ?? '');
$payment    = null;
$errors     = [];

if ($receipt_no !== '') {
    $stmt = mysqli_prepare($conn, "
        SELECT fp.*,
               s.first_name, s.last_name, s.admission_no, s.roll_no,
               c.class_name, sec.section_name,
               fs.fee_title, fs.amount AS structure_amount,
               sfa.assigned_amount, sfa.discount_amount, sfa.net_amount AS remaining_net, sfa.status AS assignment_status,
               (SELECT IFNULL(SUM(amount_paid), 0) FROM fee_payments WHERE student_fee_id = fp.student_fee_id) AS total_paid_so_far
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
        JOIN fee_structures fs ON sfa.fee_structure_id = fs.id
        WHERE fp.receipt_no = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $receipt_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $payment = $row;
    } else {
        $errors[] = "Receipt '{$receipt_no}' not found.";
    }
    mysqli_stmt_close($stmt);
} else {
    $errors[] = 'No receipt number provided.';
}

// School settings
$ss = [];
$sr = mysqli_query($conn, "SELECT setting_key, setting_value FROM school_settings");
while ($r = mysqli_fetch_assoc($sr)) $ss[$r['setting_key']] = $r['setting_value'];
$school_name    = $ss['school_name']    ?? 'Brightway School';
$school_address = $ss['school_address'] ?? '124 Education Avenue';
$school_phone   = $ss['school_phone']   ?? '';
$school_email   = $ss['school_email']   ?? '';
$currency       = $ss['currency_symbol'] ?? 'Rs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($receipt_no); ?> – <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:#f1f5f9;color:#1e293b;padding:24px 16px;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;}
        .receipt-container{width:100%;max-width:700px;}
        .receipt-actions{display:flex;gap:10px;margin-bottom:16px;justify-content:flex-end;flex-wrap:wrap;}
        .btn-print{background:#0d9488;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;}
        .btn-back{background:#334155;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:.9rem;font-weight:600;}
        .receipt-card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);overflow:hidden;}
        .receipt-header{background:linear-gradient(135deg,#0d9488 0%,#065f46 100%);color:#fff;padding:28px 32px;text-align:center;}
        .receipt-header h1{font-size:1.5rem;font-weight:800;letter-spacing:-.5px;}
        .receipt-header p{font-size:.85rem;opacity:.85;margin-top:4px;}
        .receipt-badge{display:inline-block;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:4px 14px;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-top:10px;}
        .receipt-body{padding:28px 32px;}
        .receipt-meta{display:grid;grid-template-columns:1fr 1fr;gap:16px 32px;margin-bottom:24px;padding-bottom:20px;border-bottom:2px dashed #e2e8f0;}
        .meta-item label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:3px;}
        .meta-item span{font-size:.95rem;font-weight:600;color:#1e293b;}
        .fee-detail-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9;}
        .fee-detail-row:last-child{border-bottom:none;}
        .fee-detail-row .label{color:#475569;font-size:.9rem;}
        .fee-detail-row .value{font-weight:600;font-size:.95rem;}
        .total-row{background:#f0fdf4;border-radius:8px;padding:14px 16px;margin-top:16px;display:flex;justify-content:space-between;align-items:center;}
        .total-row .label{font-size:1rem;font-weight:700;color:#065f46;}
        .total-row .value{font-size:1.3rem;font-weight:800;color:#0d9488;}
        .balance-row{background:#fef2f2;border-radius:8px;padding:10px 16px;margin-top:8px;display:flex;justify-content:space-between;align-items:center;}
        .balance-row .label{font-size:.9rem;font-weight:600;color:#991b1b;}
        .balance-row .value{font-weight:700;color:#dc2626;}
        .receipt-footer{background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;text-align:center;font-size:.78rem;color:#64748b;}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase;}
        .status-paid{background:#dcfce7;color:#166534;}
        .status-partial{background:#fef9c3;color:#854d0e;}
        .status-unpaid{background:#fee2e2;color:#991b1b;}
        @media print {
            body{background:#fff;padding:0;}
            .receipt-actions{display:none!important;}
            .receipt-card{box-shadow:none;border-radius:0;}
        }
    </style>
</head>
<body>
<div class="receipt-container">
    <div class="receipt-actions no-print">
        <a href="<?php echo $payment ? 'fee_payments.php?student_id='.(int)$payment['student_id'] : 'fee_payments.php'; ?>" class="btn-back">← Back</a>
        <button class="btn-print" onclick="window.print();">🖨 Print Receipt</button>
    </div>

    <?php if (!empty($errors)): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:16px 20px;border-radius:10px;margin-bottom:16px;">
        <?php foreach ($errors as $e): ?><p><?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
    </div>
    <?php elseif ($payment): ?>
    <div class="receipt-card">
        <!-- Header -->
        <div class="receipt-header">
            <h1><?php echo htmlspecialchars($school_name); ?></h1>
            <p><?php echo htmlspecialchars($school_address); ?>
               <?php if ($school_phone): ?> | <?php echo htmlspecialchars($school_phone); ?><?php endif; ?>
            </p>
            <div class="receipt-badge">Official Fee Receipt</div>
        </div>

        <!-- Body -->
        <div class="receipt-body">
            <!-- Meta -->
            <div class="receipt-meta">
                <div class="meta-item">
                    <label>Receipt Number</label>
                    <span style="color:#0d9488;font-size:1rem;"><?php echo htmlspecialchars($payment['receipt_no']); ?></span>
                </div>
                <div class="meta-item">
                    <label>Payment Date</label>
                    <span><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></span>
                </div>
                <div class="meta-item">
                    <label>Student Name</label>
                    <span><?php echo htmlspecialchars($payment['first_name'].' '.$payment['last_name']); ?></span>
                </div>
                <div class="meta-item">
                    <label>Admission Number</label>
                    <span><?php echo htmlspecialchars($payment['admission_no']); ?></span>
                </div>
                <?php if (!empty($payment['class_name'])): ?>
                <div class="meta-item">
                    <label>Class / Section</label>
                    <span><?php echo htmlspecialchars($payment['class_name'].' '.($payment['section_name']??'')); ?></span>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <label>Payment Method</label>
                    <span><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$payment['payment_method']))); ?></span>
                </div>
                <?php if ($payment['reference_no']): ?>
                <div class="meta-item">
                    <label>Reference No.</label>
                    <span><?php echo htmlspecialchars($payment['reference_no']); ?></span>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <label>Assignment Status</label>
                    <span class="status-badge status-<?php echo htmlspecialchars($payment['assignment_status']); ?>"><?php echo ucfirst($payment['assignment_status']); ?></span>
                </div>
            </div>

            <!-- Fee details -->
            <h3 style="font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:12px;">Fee Details</h3>

            <div class="fee-detail-row">
                <span class="label">Fee Title</span>
                <span class="value"><?php echo htmlspecialchars($payment['fee_title']); ?></span>
            </div>
            <div class="fee-detail-row">
                <span class="label">Original Amount</span>
                <span class="value"><?php echo $currency; ?> <?php echo number_format($payment['structure_amount'], 2); ?></span>
            </div>
            <?php if ((float)$payment['discount_amount'] > 0): ?>
            <div class="fee-detail-row">
                <span class="label">Discount / Concession</span>
                <span class="value" style="color:#16a34a;">- <?php echo $currency; ?> <?php echo number_format($payment['discount_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="fee-detail-row">
                <span class="label">Total Paid (all payments)</span>
                <span class="value"><?php echo $currency; ?> <?php echo number_format($payment['total_paid_so_far'], 2); ?></span>
            </div>

            <!-- Total this payment -->
            <div class="total-row">
                <span class="label">Amount Received (This Payment)</span>
                <span class="value"><?php echo $currency; ?> <?php echo number_format($payment['amount_paid'], 2); ?></span>
            </div>

            <?php if ((float)$payment['remaining_net'] > 0.009): ?>
            <div class="balance-row">
                <span class="label">Remaining Balance</span>
                <span class="value"><?php echo $currency; ?> <?php echo number_format($payment['remaining_net'], 2); ?></span>
            </div>
            <?php else: ?>
            <div style="background:#f0fdf4;border-radius:8px;padding:10px 16px;margin-top:8px;text-align:center;font-weight:700;color:#16a34a;">
                ✅ Fee Fully Cleared
            </div>
            <?php endif; ?>

            <?php if ($payment['remarks']): ?>
            <div style="margin-top:16px;padding:12px 16px;background:#f8fafc;border-radius:8px;font-size:.85rem;color:#475569;">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($payment['remarks']); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <p>This is a computer-generated receipt. Valid without signature.</p>
            <p style="margin-top:4px;"><?php echo htmlspecialchars($school_name); ?> | Generated: <?php echo date('d M Y, h:i A'); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
