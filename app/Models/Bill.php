<?php
namespace App\Models;
use App\Core\Model;

class Bill extends Model{
    protected string $table='bill';
    protected string $primaryKey='bill_id';

    public function createBill(array $data): int{
          $this->beginTransaction();

    try {
        $billId = $this->insert($data);
        $billNumber = $this->generateBillNumber((int) $billId);
        $this->update((int) $billId, ['bill_number' => $billNumber]);

        $this->commit();
        return (int) $billId;

    } catch (\Exception $e) {
        $this->rollBack();
        throw $e;
    }  
    }

    private function generateBillNumber(int $billId): string{
        $date = date('Y');
        $billNumber = 'BILL-' . $date . '-' . str_pad((string)$billId, 6, '0', STR_PAD_LEFT);
        return $billNumber;
    }

    public function findByOrderId(int $order_id): array|false{
        $stmt= $this->db->prepare("SELECT * FROM {$this->table} WHERE order_id = :order_id LIMIT 1");
        $stmt->execute (['order_id' => $order_id]);
        return $stmt->fetch();
    }

    public function findWithDetails(int $billId): array|false{
        $stmt = $this->db->prepare("SELECT bill.*, orders.order_number, customer.full_name
                FROM bill
                JOIN orders ON bill.order_id = orders.order_id
                JOIN customer ON orders.customer_id = customer.customer_id
                WHERE bill.bill_id = :billId LIMIT 1");
        $stmt->execute(['billId' => $billId]);
        return $stmt->fetch();
    }

    public function historyForCustomer(int $customerId): array{
        $stmt = $this->db->prepare("SELECT bill.*, orders.order_number
        FROM bill
        JOIN orders ON bill.order_id = orders.order_id
        WHERE orders.customer_id = :customerId
        ORDER BY bill.generated_at DESC");
        $stmt->execute(['customerId' => $customerId]);
        return $stmt->fetchAll();
    }

    public function outstanding(): array{
        $stmt = $this->db->prepare("SELECT bill.*, orders.order_number, customer.full_name
            FROM bill
            JOIN orders ON bill.order_id = orders.order_id
            JOIN customer ON orders.customer_id = customer.customer_id
            WHERE bill.outstanding_amount > 0
            ORDER BY bill.generated_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function applyPayment(int $billId, string $amount): bool{
    $this->beginTransaction();

    try {
        $stmt = $this->db->prepare("UPDATE bill SET paid_amount = paid_amount + :amount WHERE bill_id = :billId");
        $stmt->execute(['amount' => $amount, 'billId' => $billId]);

        $bill = $this->find($billId);

        if ($bill['paid_amount'] >= $bill['total_amount']) {
            $status = 'paid';
        } else {
            $status = 'partially_paid';
        }

        $this->update($billId, ['payment_status' => $status]);

        $this->commit();
        return true;

    } catch (\Exception $e) {
        $this->rollBack();
        throw $e;
    }
    }

    public function updateTotals(int $billId, array $totals): bool{
        $allowed = ['subtotal'=> true, 'tax_amount' => true, 'discount_amount' =>true, 'total_amount' =>true];
       $filtered = array_intersect_key($totals, $allowed);
           return $this->update($billId, $filtered);
    }

    public function totalOutstanding(): string{
    $stmt = $this->db->prepare("SELECT SUM(outstanding_amount) as total_outstanding FROM bill");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total_outstanding'] ?? '0.00';
    }

    public function markRefunded(int $billId): bool{
    return $this->update($billId, ['payment_status' => 'refunded']);
    }
}