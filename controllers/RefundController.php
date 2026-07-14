<?php
require_once __DIR__ . '/../models/RefundRequest.php';

class RefundController
{
    private $refundModel;

    public function __construct()
    {
        $this->refundModel = new RefundRequest();
    }

    public function cancelAppointment($appointmentId, $cancelledBy, $reason, $patientId = null, $hospitalId = null)
    {
        $appointment = $this->refundModel->findAppointmentForRefund($appointmentId, $patientId, $hospitalId);
        if (!$appointment || !in_array($appointment['status'], ['pending', 'confirmed', 'cancel_pending', 'cancelled'])) {
            return ['success' => false, 'message' => 'Không tìm thấy lịch khám hợp lệ để hủy.'];
        }

        $db = new Database();
        $db->query("UPDATE appointments SET status = 'cancelled' WHERE id = :id");
        $db->bind(':id', $appointmentId);
        $db->execute();

        $db->query("UPDATE schedules SET status = 'available' WHERE id = :schedule_id");
        $db->bind(':schedule_id', (int)$appointment['schedule_id']);
        $db->execute();

        $refund = $this->refundModel->createForAppointment($appointment, $cancelledBy, $reason);
        if ($refund['created']) {
            return ['success' => true, 'message' => 'Đã hủy lịch và tạo yêu cầu hoàn tiền ' . $refund['rate'] . '%.'];
        }

        return ['success' => true, 'message' => 'Đã hủy lịch. Lịch này không đủ điều kiện hoàn tiền.'];
    }

    public function index($status = '', $hospitalId = null)
    {
        return $this->refundModel->all($status, $hospitalId);
    }

    public function approve($id, $adminId)
    {
        return $this->refundModel->updateStatus($id, 'refunded', $adminId);
    }

    public function reject($id, $adminId)
    {
        return $this->refundModel->updateStatus($id, 'rejected', $adminId);
    }
}
?>
