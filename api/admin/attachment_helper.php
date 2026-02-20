<?php
/**
 * Helper function to display attachment info
 * Include this in your ticket display pages
 * 
 * Usage: get_attachment_info($ticket_id, $attachment_filename)
 */

function get_attachment_info($ticket_id, $attachment_filename) {
    if (empty($attachment_filename)) {
        return '<span style="color: #999;">No attachment</span>';
    }
    
    $file_path = __DIR__ . '/uploads/tickets/' . $attachment_filename;
    
    if (!file_exists($file_path)) {
        return '<span style="color: #d32f2f;">File not found</span>';
    }
    
    $file_size = filesize($file_path);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    $file_ext = strtoupper(pathinfo($attachment_filename, PATHINFO_EXTENSION));
    
    $download_url = "download_attachment.php?ticket_id=" . $ticket_id . "&file=" . urlencode($attachment_filename);
    
    return '
    <a href="' . $download_url . '" class="attachment-link" style="
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #333;
        font-size: 14px;
    ">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span>' . basename($attachment_filename) . '</span>
        <span style="color: #999; font-size: 12px;">(' . $file_size_mb . ' MB)</span>
    </a>
    ';
}
?>
