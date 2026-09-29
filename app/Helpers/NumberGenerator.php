<?php

namespace App\Helpers;

use App\Core\Database;

class NumberGenerator
{
    public static function orderNumber(): string
    {
        return self::generate('ORD', 'orders', 'order_number');
    }

    public static function billNumber(): string
    {
        return self::generate('BILL', 'bill', 'bill_number');
    }

    public static function receiptNumber(): string
    {
        return self::generate('RCPT', 'receipt', 'receipt_number');
    }

    public static function invoiceNumber(): string
    {
        return self::generate('INV', 'invoice', 'invoice_number');
    }

    public static function complaintNumber(): string
    {
        return self::generate('CMP', 'complaint', 'complaint_number');
    }

    public static function pickupRequestNumber(): string
    {
        return self::generate('PU', 'pickup_request', 'request_number');
    }

    private static function generate(string $prefix, string $table, string $column): string
    {
        $db = Database::getConnection();
        $datePart = date('Ymd');

        do {
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $candidate = "{$prefix}-{$datePart}-{$random}";

            $stmt = $db->prepare("SELECT 1 FROM {$table} WHERE {$column} = :value LIMIT 1");
            $stmt->execute(['value' => $candidate]);
            $exists = $stmt->fetch();
        } while ($exists);

        return $candidate;
    }
}