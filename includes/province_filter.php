<?php
// Danh sách tỉnh/thành chuẩn của Việt Nam (dùng để đối chiếu địa chỉ cơ sở)
$vnProvinces = [
    'Thành phố Hà Nội', 'Thành phố Hồ Chí Minh', 'Thành phố Hải Phòng', 'Thành phố Đà Nẵng', 'Thành phố Cần Thơ',
    'Tỉnh An Giang', 'Tỉnh Bà Rịa - Vũng Tàu', 'Tỉnh Bắc Giang', 'Tỉnh Bắc Kạn', 'Tỉnh Bạc Liêu',
    'Tỉnh Bắc Ninh', 'Tỉnh Bến Tre', 'Tỉnh Bình Định', 'Tỉnh Bình Dương', 'Tỉnh Bình Phước',
    'Tỉnh Bình Thuận', 'Tỉnh Cà Mau', 'Tỉnh Cao Bằng', 'Tỉnh Đắk Lắk', 'Tỉnh Đắk Nông',
    'Tỉnh Điện Biên', 'Tỉnh Đồng Nai', 'Tỉnh Đồng Tháp', 'Tỉnh Gia Lai', 'Tỉnh Hà Giang',
    'Tỉnh Hà Nam', 'Tỉnh Hà Tĩnh', 'Tỉnh Hải Dương', 'Tỉnh Hậu Giang', 'Tỉnh Hòa Bình',
    'Tỉnh Hưng Yên', 'Tỉnh Khánh Hòa', 'Tỉnh Kiên Giang', 'Tỉnh Kon Tum', 'Tỉnh Lai Châu',
    'Tỉnh Lâm Đồng', 'Tỉnh Lạng Sơn', 'Tỉnh Lào Cai', 'Tỉnh Long An', 'Tỉnh Nam Định',
    'Tỉnh Nghệ An', 'Tỉnh Ninh Bình', 'Tỉnh Ninh Thuận', 'Tỉnh Phú Thọ', 'Tỉnh Phú Yên',
    'Tỉnh Quảng Bình', 'Tỉnh Quảng Nam', 'Tỉnh Quảng Ngãi', 'Tỉnh Quảng Ninh', 'Tỉnh Quảng Trị',
    'Tỉnh Sóc Trăng', 'Tỉnh Sơn La', 'Tỉnh Tây Ninh', 'Tỉnh Thái Bình', 'Tỉnh Thái Nguyên',
    'Tỉnh Thanh Hóa', 'Tỉnh Thừa Thiên Huế', 'Tỉnh Tiền Giang', 'Tỉnh Trà Vinh', 'Tỉnh Tuyên Quang',
    'Tỉnh Vĩnh Long', 'Tỉnh Vĩnh Phúc', 'Tỉnh Yên Bái'
];

/**
 * Lấy danh sách tỉnh/thành có cơ sở y tế đã đăng ký (đã duyệt), kèm số lượng.
 * Trả về mảng [['name' => 'Thành phố Cần Thơ', 'count' => 7], ...]
 * $extraCondition: điều kiện SQL bổ sung trên alias h (vd: chỉ cơ sở có gói chụp phim).
 */
function getRegisteredProvinces($db, $vnProvinces, $extraCondition = '') {
    $extra = $extraCondition !== '' ? ' AND ' . $extraCondition : '';
    try {
        $db->query("SELECT h.address
                    FROM hospitals h
                    INNER JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                    WHERE COALESCE(u.hospital_approval_status, 'approved') = 'approved'
                      AND h.address IS NOT NULL AND h.address <> ''
                      {$extra}");
        $rows = $db->resultSet();
    } catch (Exception $e) {
        $rows = [];
    }
    $result = [];
    foreach ($vnProvinces as $province) {
        $short = trim(str_replace(['Thành phố ', 'Tỉnh '], '', $province));
        $count = 0;
        foreach ($rows as $row) {
            $address = $row['address'];
            if (mb_stripos($address, $province) !== false || mb_stripos($address, $short) !== false) {
                $count++;
            }
        }
        if ($count > 0) {
            $result[] = ['name' => $province, 'count' => $count];
        }
    }
    return $result;
}
