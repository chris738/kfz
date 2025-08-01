<?php
/**
 * German localization helper functions for KFZ application
 * Handles German number and date formats for input processing
 */

/**
 * Convert German decimal format (comma) to English (dot) for database storage
 * @param string $german_number Number in German format (e.g., "1,45")
 * @return float Number in English format for database
 */
function parse_german_number($german_number) {
    if (empty($german_number)) {
        return 0;
    }
    
    // Remove any thousands separators (dots) and replace decimal comma with dot
    $english_number = str_replace('.', '', $german_number); // Remove thousands separators
    $english_number = str_replace(',', '.', $english_number); // Replace decimal comma with dot
    
    return floatval($english_number);
}

/**
 * Convert German date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd) for database storage
 * @param string $german_date Date in German format (e.g., "25.12.2023")
 * @return string Date in ISO format for database or empty string if invalid
 */
function parse_german_date($german_date) {
    if (empty($german_date)) {
        return '';
    }
    
    // Check if it's already in ISO format (yyyy-mm-dd)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $german_date)) {
        return $german_date;
    }
    
    // Parse German format (dd.mm.yyyy)
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $german_date, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        
        // Validate the date
        if (checkdate($month, $day, $year)) {
            return "$year-$month-$day";
        }
    }
    
    return '';
}

/**
 * Format date from ISO format to German format for display
 * @param string $iso_date Date in ISO format (yyyy-mm-dd)
 * @return string Date in German format (dd.mm.yyyy)
 */
function format_german_date($iso_date) {
    if (empty($iso_date)) {
        return '';
    }
    
    return date('d.m.Y', strtotime($iso_date));
}

/**
 * Get current date in German format
 * @return string Current date in dd.mm.yyyy format
 */
function current_german_date() {
    return date('d.m.Y');
}
?>