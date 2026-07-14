<?php
require_once __DIR__ . '/../models/MedicalResult.php';

class MedicalResultController
{
    private $model;

    public function __construct()
    {
        $this->model = new MedicalResult();
    }

    public function startExam($appointmentId, $hospitalId)
    {
        $appointment = $this->model->findAppointment($appointmentId, $hospitalId);
        if (!$appointment || $appointment['status'] !== 'confirmed') {
            return ['success' => false, 'message' => 'Không thể chuyển lịch này sang trạng thái đang khám.'];
        }

        $db = new Database();
        $db->query("UPDATE appointments SET status = 'examining' WHERE id = :id");
        $db->bind(':id', $appointmentId);
        $db->execute();
        return ['success' => true, 'message' => 'Đã chuyển sang trạng thái Đang khám.'];
    }

    public function completeExam($post, $files, $hospitalId)
    {
        $appointmentId = (int)($post['appointment_id'] ?? 0);
        $appointment = $this->model->findAppointment($appointmentId, $hospitalId);
        if (!$appointment || !in_array($appointment['status'], ['examining', 'completed'], true)) {
            return ['success' => false, 'message' => 'Chỉ có thể nhập hoặc sửa kết quả của lịch đang khám và đã hoàn thành.'];
        }

        $diagnosis = trim($post['diagnosis'] ?? '');
        $conclusion = trim($post['conclusion'] ?? '');
        $prescription = trim($post['prescription'] ?? '');
        if ($diagnosis === '' || $conclusion === '' || $prescription === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ chẩn đoán, kết luận và đơn thuốc.'];
        }

        $pdfFile = '';
        if (!empty($files['pdf_file']['name'])) {
            if (($files['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Không thể upload file PDF.'];
            }
            $extension = strtolower(pathinfo($files['pdf_file']['name'], PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                return ['success' => false, 'message' => 'File kết quả phải là PDF.'];
            }
            if (($files['pdf_file']['size'] ?? 0) > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'File PDF không được vượt quá 5MB.'];
            }
            $uploadDir = __DIR__ . '/../uploads/medical_results/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = 'result_' . $appointmentId . '_' . time() . '.pdf';
            if (!move_uploaded_file($files['pdf_file']['tmp_name'], $uploadDir . $fileName)) {
                return ['success' => false, 'message' => 'Không thể lưu file PDF.'];
            }
            $pdfFile = 'uploads/medical_results/' . $fileName;
        }

        $this->model->save([
            'appointment_id' => $appointmentId,
            'doctor_id' => (int)$appointment['doctor_id'],
            'diagnosis' => $diagnosis,
            'conclusion' => $conclusion,
            'prescription' => $prescription,
            'note' => trim($post['note'] ?? ''),
            're_exam_date' => trim($post['re_exam_date'] ?? ''),
            'pdf_file' => $pdfFile
        ]);

        return ['success' => true, 'message' => 'Đã lưu kết quả khám và hoàn thành lịch khám.'];
    }

    public function getResultForPatient($appointmentId, $patientId)
    {
        return $this->model->getByAppointment($appointmentId, $patientId);
    }
}
?>
