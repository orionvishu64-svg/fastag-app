<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

// CLI-safe absolute path
require_once '/opt/bitnami/apache/htdocs/fastag_website/config/db.php';

try {
    $sql = "
        UPDATE payments p
        JOIN orders o ON o.id = p.order_id
        SET
            p.status = 'FAILED',
            p.raw_response = IFNULL(p.raw_response, 'AUTO-FAILED BY CRON'),
            o.payment_status = 'failed',
            o.updated_at = NOW()
        WHERE
            p.status = 'PENDING'
            AND (
                (p.expires_at IS NOT NULL AND p.expires_at < NOW())
                OR
                (p.raw_response IS NOT NULL AND p.raw_response LIKE '%\"FAILURE\"%')
            )
    ";

    $affected = $pdo->exec($sql);
    error_log('[UPI CRON] Failed payments updated: ' . (int)$affected);

} catch (Throwable $e) {
    error_log('[UPI CRON ERROR] ' . $e->getMessage());
}
