document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-result-appointment]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.querySelector('.medical-result-form');
            var isEdit = button.dataset.resultMode === 'edit';

            form.reset();
            form.classList.remove('was-validated');
            document.getElementById('resultAppointmentId').value = button.dataset.resultAppointment;
            document.getElementById('resultPatientName').textContent = button.dataset.resultPatient || '';
            form.elements.diagnosis.value = button.dataset.resultDiagnosis || '';
            form.elements.conclusion.value = button.dataset.resultConclusion || '';
            form.elements.prescription.value = button.dataset.resultPrescription || '';
            form.elements.note.value = button.dataset.resultNote || '';
            form.elements.re_exam_date.value = button.dataset.resultReExamDate || '';
            document.getElementById('medicalResultModalTitle').textContent = isEdit ? 'Chỉnh sửa kết quả khám' : 'Nhập kết quả khám';
            document.getElementById('medicalResultSubmitButton').textContent = isEdit ? 'Xác nhận cập nhật' : 'Xác nhận lưu kết quả';
        });
    });

    document.querySelectorAll('.medical-result-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});
