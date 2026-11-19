<?php
// Shared address rules for add, edit and save pages

if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

const ADDRESS_STATES = [
    'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
    'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
    'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
    'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
    'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim',
    'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
];

const ADDRESS_FIELDS = [
    'name', 'phone', 'alt_phone', 'address_line', 'locality',
    'landmark', 'city', 'state', 'pincode', 'address_type',
];

// Reads and trims the address fields from a request array
function address_read(array $src): array {
    $out = [];
    foreach (ADDRESS_FIELDS as $f) {
        $out[$f] = trim((string)($src[$f] ?? ''));
    }
    return $out;
}

// Returns an error message, or null when the address is valid
function address_error(array $in): ?string {
    if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 100) {
        return 'Please enter the full name (2 to 100 characters).';
    }
    if (!preg_match('/^[6-9]\d{9}$/', $in['phone'])) {
        return 'Please enter a valid 10-digit mobile number.';
    }
    if ($in['alt_phone'] !== '' && !preg_match('/^[6-9]\d{9}$/', $in['alt_phone'])) {
        return 'Please enter a valid alternate phone number, or leave it empty.';
    }
    if (mb_strlen($in['address_line']) < 5 || mb_strlen($in['address_line']) > 255) {
        return 'Please enter the flat, street and area (5 to 255 characters).';
    }
    if (mb_strlen($in['locality']) > 100) {
        return 'Locality is too long.';
    }
    if (mb_strlen($in['landmark']) > 150) {
        return 'Landmark is too long.';
    }
    if (mb_strlen($in['city']) < 2 || mb_strlen($in['city']) > 100) {
        return 'Please enter the city, district or town.';
    }
    if (!in_array($in['state'], ADDRESS_STATES, true)) {
        return 'Please select a valid state.';
    }
    if (!preg_match('/^\d{6}$/', $in['pincode'])) {
        return 'Please enter a valid 6-digit pincode.';
    }
    if (!in_array($in['address_type'], ['Home', 'Work'], true)) {
        return 'Please choose Home or Work.';
    }
    return null;
}

// Empty optional fields are stored as NULL
function address_nullable(string $value): ?string {
    return $value !== '' ? $value : null;
}