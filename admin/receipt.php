<?php
/**
 * admin/receipt.php
 * Fee Payment Receipt (Clean, Printable, Professional)
 * Brightway School Management System
 */
require_once __DIR__ . '/auth.php';
requireAdminLogin();

$receipt_no = trim($_GET['receipt_no'] ?? '');
$errors = [];
$payment = null;

if ($receipt_no !== '') {
    $stmt = mysqli_prepare($conn, "SELECT fp.*, 
        s.first_name, s.last_name, s.admission_no, s.roll_no,
        c.class_name, sec.section_name,
        f.fee_title, f.amount AS structure_amount,
        sfa.assigned_amount, sfa.discount_amount, sfa.net_amount AS remaining_net, sfa.status AS assignment_status,
        (SELECT IFNULL(SUM(amount_paid), 0) FROM fee_payments WHERE student_fee_id = fp.student_fee_id) AS total_paid_so_far
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        JOIN student_fee_assignments sfa ON fp.student_fee_id = sfa.id
        JOIN fee_structures f ON sfa.fee_structure_id = f.id
        WHERE fp.receipt_no = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $receipt_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $payment = $row;
    } else {
        $errors[] = "Receipt '{$receipt_no}' not found in system.";
    }
    mysqli_stmt_close($stmt);
} else {
    $errors[] = 'No receipt number specified.';
}

// Fetch school settings for header
$school_settings = [];
$s_res = mysqli_query($conn, "SELECT setting_key, setting_value FROM school_settings");
while ($sr = mysqli_fetch_assoc($s_res)) {
    $school_settings[$sr['setting_key']] = $sr['setting_value'];
}
$school_name = $school_settings['school_name'] ?? 'Brightway School';
$school_address = $school_settings['school_address'] ?? '124 Education Avenue, Learning District';
$school_phone = $school_settings['school_phone'] ?? '+1 (555) 234-5678';
$school_email = $school_settings['school_email'] ?? 'info@brightwayschool.edu';
$currency = $school_settings['currency_symbol'] ?? 'Rs';

// If print view parameter is requested or standalone
$is_standalone = isset($_GET['print']) || isset($_GET['clean']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($receipt_no); ?> – <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="icon" href="../assets/images/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .receipt-container {
            width: 100%;
            max-width: 680px;
        }
        .receipt-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: 0.2s;
        }
        .btn-primary { background: #0d9488; color: #fff; }
        .btn-primary:hover { background: #0f766e; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        
        .receipt-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            position: relative;
        }
        .receipt-badge-watermark {
            position: absolute;
            top: 40px;
            right: 40px;
            border: 2px solid #0d9488;
            color: #0d9488;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .receipt-header {
            border-bottom: 2px solid #0d9488;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .receipt-school-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
        }
        .receipt-school-meta {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 4px;
            line-height: 1.5;
        }
        .receipt-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 28px;
        }
        .meta-item {
            font-size: 0.85rem;
        }
        .meta-label {
            color: #64748b;
            font-weight: 500;
            margin-bottom: 2px;
        }
        .meta-val {
            color: #0f172a;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .receipt-table th {
            text-align: left;
            background: #f1f5f9;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #cbd5e1;
        }
        .receipt-table td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .receipt-total-row td {
            font-size: 1.1rem;
            font-weight: 800;
            background: #f0fdfa;
            color: #0d9488;
            border-top: 2px solid #0d9488;
        }
        .receipt-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
        }
        .sign-box {
            text-align: center;
            width: 180px;
        }
        .sign-line {
            border-bottom: 1px solid #475569;
            height: 36px;
            margin-bottom: 6px;
        }
        .sign-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #fca5a5;
            margin-bottom: 20px;
        }

        /* ─── PRINT MEDIA OPTIMIZATION ─────────────────────────── */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .receipt-actions {
                display: none !important;
            }
            .receipt-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .receipt-container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="receipt-actions">
        <a href="fee_payments.php" class="btn btn-secondary">
            ← Back to Fee Payments
        </a>
        <button type="button" onclick="window.print();" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Official Receipt
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php echo implode('<br>', $errors); ?>
        </div>
    <?php elseif ($payment): 
        $remaining = max(0, (float)$payment['remaining_net']);
    ?>
        <div class="receipt-card">
            <div class="receipt-badge-watermark">Official Receipt</div>

            <!-- Header -->
            <div class="receipt-header">
                <div class="receipt-school-name"><?php echo htmlspecialchars($school_name); ?></div>
                <div class="receipt-school-meta">
                    <?php echo htmlspecialchars($school_address); ?><br>
                    Phone: <?php echo htmlspecialchars($school_phone); ?> | Email: <?php echo htmlspecialchars($school_email); ?>
                </div>
            </div>

            <!-- Meta Information Grid -->
            <div class="receipt-meta-grid">
                <div class="meta-item">
                    <div class="meta-label">Receipt Number</div>
                    <div class="meta-val" style="color:#0d9488;"><?php echo htmlspecialchars($payment['receipt_no']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Payment Date</div>
                    <div class="meta-val"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Student Name</div>
                    <div class="meta-val"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Admission # / Roll #</div>
                    <div class="meta-val"><?php echo htmlspecialchars($payment['admission_no']); ?> <?php if ($payment['roll_no']) echo "(Roll #{$payment['roll_no']})"; ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Class & Section</div>
                    <div class="meta-val"><?php echo htmlspecialchars(($payment['class_name'] ?? 'N/A') . ' - ' . ($payment['section_name'] ?? 'A')); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Payment Mode & Ref</div>
                    <div class="meta-val"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?><?php if ($payment['reference_no']) echo " (Ref: " . htmlspecialchars($payment['reference_no']) . ")"; ?></div>
                </div>
            </div>

            <!-- Fee Line Items Table -->
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Description / Fee Category</th>
                        <th style="text-align:right;">Assigned Amount</th>
                        <th style="text-align:right;">Concession</th>
                        <th style="text-align:right;">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['fee_title']); ?></strong>
                            <?php if ($payment['remarks']): ?>
                                <div style="font-size:0.8rem;color:#64748b;margin-top:2px;">Note: <?php echo htmlspecialchars($payment['remarks']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;"><?php echo $currency; ?> <?php echo number_format($payment['assigned_amount'], 2); ?></td>
                        <td style="text-align:right;color:#64748b;"><?php echo $currency; ?> <?php echo number_format($payment['discount_amount'], 2); ?></td>
                        <td style="text-align:right;font-weight:700;color:#0d9488;"><?php echo $currency; ?> <?php echo number_format($payment['amount_paid'], 2); ?></td>
                    </tr>
                    <tr class="receipt-total-row">
                        <td>Amount Received in Words: <span style="font-size:0.85rem;font-weight:600;color:#334155;text-transform:capitalize;"><?php echo $currency; ?> <?php echo number_format($payment['amount_paid'], 2); ?> only</span></td>
                        <td colspan="2" style="text-align:right;">Total Paid:</td>
                        <td style="text-align:right;"><?php echo $currency; ?> <?php echo number_format($payment['amount_paid'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Balance Summary Box -->
            <div style="background:#f8fafc;padding:12px 16px;border-radius:6px;border:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;font-size:0.9rem;">
                <div>
                    Status: <strong style="color:<?php echo $remaining == 0 ? '#0d9488' : '#d97706'; ?>;"><?php echo $remaining == 0 ? 'Full Payment Cleared' : 'Partial Payment'; ?></strong>
                </div>
                <div>
                    Remaining Outstanding Balance: <strong style="color:<?php echo $remaining > 0 ? '#dc2626' : '#0d9488'; ?>;"><?php echo $currency; ?> <?php echo number_format($remaining, 2); ?></strong>
                </div>
            </div>

            <!-- Footer Signatures -->
            <div class="receipt-footer">
                <div style="font-size:0.75rem;color:#94a3b8;line-height:1.4;">
                    * Computer-generated official receipt.<br>
                    Generated on <?php echo date('Y-m-d H:i:s'); ?>.
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-label">Authorized Stamp & Sign</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
