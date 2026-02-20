<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

// Handle AJAX request for ticket details
if (isset($_GET['action']) && $_GET['action'] == 'get_ticket' && isset($_GET['ticket_id'])) {
    header('Content-Type: application/json');
    $ticket_id = (int)$_GET['ticket_id'];
    
    $sql = "SELECT 
        t.ticket_id,
        t.subject_,
        t.description_,
        t.status_,
        t.submitted_by,
        t.attachment,
        COALESCE(g.user_name, t.client_name, 'Unknown') as user_name,
        COALESCE(g.email, t.client_email, 'N/A') as email,
        COALESCE(c.category_name, 'Uncategorized') as category_name,
        t.created_at as transaction_date,
        t.date_resolved
    FROM TICKETS t
    LEFT JOIN GOOGLE_OAUTH_USERS g ON t.submitted_by = g.oauth_id
    LEFT JOIN CATEGORIES c ON t.category_id = c.category_id
    WHERE t.ticket_id = $ticket_id";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
        $ticket_no = 'TN' . str_pad($ticket['ticket_id'], 10, '0', STR_PAD_LEFT);
        $transaction_date = !empty($ticket['transaction_date']) ? date('M d, Y', strtotime($ticket['transaction_date'])) : date('M d, Y');
        
        echo json_encode([
            'success' => true,
            'ticket' => [
                'ticket_id' => $ticket['ticket_id'],
                'ticket_no' => $ticket_no,
                'subject' => $ticket['subject_'],
                'description' => $ticket['description_'],
                'status' => $ticket['status_'],
                'category' => $ticket['category_name'],
                'user_name' => $ticket['user_name'],
                'email' => $ticket['email'],
                'transaction_date' => $transaction_date,
                'attachment' => $ticket['attachment']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
    }
    exit();
}

// Handle AJAX request to update ticket status
if (isset($_GET['action']) && $_GET['action'] == 'update_ticket_status' && isset($_GET['ticket_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $ticket_id = (int)$_GET['ticket_id'];
    $new_status = isset($_GET['status']) ? trim($conn->real_escape_string($_GET['status'])) : '';
    $date_resolved = isset($_GET['date_resolved']) ? trim($_GET['date_resolved']) : '';
    
    // Validate ticket_id
    if ($ticket_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
        exit();
    }
    
    // Validate status
    if (empty($new_status)) {
        echo json_encode(['success' => false, 'message' => 'Status is required']);
        exit();
    }
    
    // Get old status for logging
    $old_status_sql = "SELECT status_ FROM TICKETS WHERE ticket_id = $ticket_id";
    $old_status_result = $conn->query($old_status_sql);
    
    if (!$old_status_result) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $old_status_data = $old_status_result->fetch_assoc();
    $old_status = $old_status_data ? $old_status_data['status_'] : 'Unknown';
    
    // Update ticket status
    try {
        if ($new_status === 'Done' && !empty($date_resolved)) {
            // Status is Done with date resolved
            $update_sql = "UPDATE TICKETS SET status_ = ?, date_resolved = ? WHERE ticket_id = ?";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ssi', $new_status, $date_resolved, $ticket_id);
        } else if ($new_status !== 'Done') {
            // Status is not Done, clear date_resolved
            $update_sql = "UPDATE TICKETS SET status_ = ?, date_resolved = NULL WHERE ticket_id = ?";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('si', $new_status, $ticket_id);
        } else {
            // Status is Done without date_resolved
            $update_sql = "UPDATE TICKETS SET status_ = ? WHERE ticket_id = ?";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('si', $new_status, $ticket_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Log the activity
        $admin_id = $_SESSION['admin_id'];
        $old_value = "Status: $old_status";
        $new_value = "Status: $new_status";
        
        $log_sql = "INSERT INTO ACTIVITY_LOGS (admin_id, action_, entity_type, entity_id, old_value, new_value) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        
        if ($log_stmt) {
            $action = 'UPDATE';
            $entity_type = 'Ticket';
            $log_stmt->bind_param('ississ', $admin_id, $action, $entity_type, $ticket_id, $old_value, $new_value);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filter and search settings
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$category_filter = isset($_GET['category_filter']) ? $_GET['category_filter'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'ticket_desc';

// Build SQL query with filters
$sql = "SELECT 
    t.ticket_id,
    t.subject_,
    t.description_,
    t.status_,
    t.submitted_by,
    COALESCE(g.user_name, t.client_name, 'Unknown') as user_name,
    COALESCE(g.email, t.client_email, 'N/A') as email,
    COALESCE(c.category_name, 'Uncategorized') as category_name,
    t.created_at as transaction_date,
    t.date_resolved
FROM TICKETS t
LEFT JOIN GOOGLE_OAUTH_USERS g ON t.submitted_by = g.oauth_id
LEFT JOIN CATEGORIES c ON t.category_id = c.category_id
WHERE 1=1";

// Apply search filter
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " AND (t.subject_ LIKE '%$search_escaped%' OR t.description_ LIKE '%$search_escaped%' OR g.user_name LIKE '%$search_escaped%' OR t.client_name LIKE '%$search_escaped%')";
}

// Apply status filter
if (!empty($status_filter)) {
    $status_filter = $conn->real_escape_string($status_filter);
    $sql .= " AND t.status_ = '$status_filter'";
}

// Apply category filter
if (!empty($category_filter)) {
    $category_filter = $conn->real_escape_string($category_filter);
    $sql .= " AND c.category_name = '$category_filter'";
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM ($sql) as t_count";
$count_result = $conn->query($count_sql);
if ($count_result) {
    $row = $count_result->fetch_assoc();
    $total_records = $row ? ($row['total'] ?? 0) : 0;
} else {
    $total_records = 0;
}
$total_pages = ceil($total_records / $per_page);

// Apply sorting
switch($sort_by) {
    case 'subject_asc':
        $sql .= " ORDER BY t.subject_ ASC";
        break;
    case 'subject_desc':
        $sql .= " ORDER BY t.subject_ DESC";
        break;
    case 'status_asc':
        $sql .= " ORDER BY t.status_ ASC, t.ticket_id DESC";
        break;
    case 'category_asc':
        $sql .= " ORDER BY c.category_name ASC, t.ticket_id DESC";
        break;
    case 'ticket_oldest':
        $sql .= " ORDER BY t.ticket_id ASC";
        break;
    case 'ticket_desc':
    default:
        $sql .= " ORDER BY t.ticket_id DESC";
        break;
}

// Add pagination
$sql .= " LIMIT $per_page OFFSET $offset";

$result = $conn->query($sql);

// Fetch categories for filter dropdown
$categories_result = $conn->query("SELECT DISTINCT category_name FROM CATEGORIES ORDER BY category_name ASC");
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($cat_row = $categories_result->fetch_assoc()) {
        $categories[] = $cat_row['category_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Tickets</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
    <style>
        .notification-toast {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 400px;
            word-wrap: break-word;
        }

        .notification-toast.show {
            opacity: 1;
        }

        .notification-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .notification-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* Filter Bar Styles */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-selects {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .filter-selects select, .filter-selects input {
            padding: 0.625rem 2.5rem 0.625rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            background-color: #FFFFFF;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23374151' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .filter-selects select:hover, .filter-selects input:hover {
            border-color: #9CA3AF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filter-selects select:focus, .filter-selects input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .filter-selects select {
            cursor: pointer;
        }

        /* Search Suggestions Dropdown */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: #FFFFFF;
            border: 1px solid #D1D5DB;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .search-suggestions:not(:empty) {
            display: block;
        }

        .search-suggestions .suggestion-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            transition: background-color 0.2s;
            color: #374151;
            font-size: 0.875rem;
        }

        .search-suggestions .suggestion-item:hover {
            background-color: #F9FAFB;
        }

        .search-suggestions .suggestion-item:last-child {
            border-bottom: none;
        }

        .filters-applied-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #F3F4F6;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #1E40AF;
        }

        .filter-tag .remove-filter {
            cursor: pointer;
            display: flex;
            align-items: center;
            color: #3B82F6;
            transition: color 0.2s;
        }

        .filter-tag .remove-filter:hover {
            color: #1E40AF;
        }

        .filter-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .action-icon-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-icon-btn:hover {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        .action-icon-btn svg {
            stroke: #6B7280;
            width: 18px;
            height: 18px;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-done {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-in-progress {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .status-pending {
            background-color: #E5E7EB;
            color: #374151;
        }

        /* Clickable Table Row Styles */
        table tbody tr {
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        table tbody tr:hover {
            background-color: #F3F4F6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* View Button */
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #3B82F6;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }

        .btn-view:hover {
            background: #2563EB;
        }

        .btn-view svg {
            stroke: #FFFFFF;
            width: 16px;
            height: 16px;
        }

        /* Pagination Styles */
        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: #F9FAFB;
            border-top: 1px solid #E5E7EB;
        }

        .table-footer-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #6B7280;
        }

        .per-page-select {
            padding: 0.375rem 0.75rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            font-size: 0.875rem;
            background: #FFFFFF;
            cursor: pointer;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-btn {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            background: #FFFFFF;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        .pagination-btn.active {
            background: #3B82F6;
            color: #FFFFFF;
            border-color: #3B82F6;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Ticket Modal Styles */
        .ticket-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            right: -100%;
            width: 50%;
            height: 100%;
            background-color: #FFFFFF;
            z-index: 1000;
            overflow-y: auto;
            transition: right 0.3s ease;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.1);
        }

        .ticket-modal-overlay.active {
            display: block;
            right: 0;
        }

        /* Hide table container when modal is active */
        .ticket-modal-overlay.active ~ main .table-container {
            display: none;
        }

        .ticket-modal-overlay.active ~ main .table-footer {
            display: none;
        }

        .ticket-modal-content {
            background: #F9FAFB;
            width: 100%;
            height: 100%;
            overflow-y: auto;
            position: relative;
        }

        @keyframes slideRight {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
            }
        }

        .ticket-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #E5E7EB;
            position: sticky;
            top: 0;
            background: #FFFFFF;
            z-index: 10;
        }

        .ticket-modal-header h2 {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .ticket-modal-header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ticket-modal-close,
        .ticket-modal-refresh {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: #6B7280;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 1rem;
        }

        .ticket-modal-close:hover,
        .ticket-modal-refresh:hover {
            background: #F3F4F6;
            color: #111827;
        }

        .ticket-modal-body {
            padding: 3rem 2.5rem;
            max-width: 900px;
        }

        .ticket-section {
            margin-bottom: 3rem;
        }

        .ticket-section-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ticket-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem 2.5rem;
            margin-bottom: 2.5rem;
        }

        @media (max-width: 1400px) {
            .ticket-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .ticket-info-field label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            display: block;
        }

        .ticket-info-field input,
        .ticket-info-field .status-display {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #374151;
            background-color: #FFFFFF;
            font-family: inherit;
        }

        .ticket-info-field .status-display {
            display: inline-block;
            width: fit-content;
            padding: 0.375rem 0.875rem;
            border: none;
        }

        .ticket-problem-field {
            display: flex;
            flex-direction: column;
        }

        .ticket-problem-field label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            display: block;
        }

        .ticket-problem-field textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #374151;
            background-color: #FFFFFF;
            font-family: inherit;
            resize: vertical;
            min-height: 200px;
            line-height: 1.6;
        }

        .status-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #374151;
            background-color: #FFFFFF;
            font-family: inherit;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23374151' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5em 1.5em;
            padding-right: 2.75rem;
            transition: all 0.2s ease;
        }

        .status-select:hover {
            border-color: #9CA3AF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .status-select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .ticket-modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            padding: 1.25rem;
            border-top: 1px solid #E5E7EB;
            position: sticky;
            bottom: 0;
            background: #FFFFFF;
            z-index: 10;
        }

        .btn-update-status {
            flex: 1;
            padding: 0.75rem 1.5rem;
            background: #3B82F6;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-update-status:hover {
            background: #2563EB;
        }

        .btn-update-status:disabled {
            background: #D1D5DB;
            cursor: not-allowed;
        }

        .btn-cancel {
            flex: 1;
            padding: 0.75rem 1.5rem;
            background: #F3F4F6;
            color: #374151;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #E5E7EB;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="content-header">
                <h1>Tickets</h1>
                <p>Manage the Tickets for continuous support for users.</p>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="search-container" style="flex: 1; min-width: 250px; position: relative;">
                    <input type="text" id="searchInput" placeholder="Search tickets..." value="<?= htmlspecialchars($search) ?>" style="width: 100%; padding: 0.625rem 1rem; border: 1px solid #D1D5DB; border-radius: 4px; font-size: 0.875rem;">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>

                <div class="filter-selects">
                    <div class="filter-group">
                        <label for="category_filter_select">Category:</label>
                        <select id="category_filter_select" onchange="applyFilters()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="status_filter_select">Status:</label>
                        <select id="status_filter_select" onchange="applyFilters()">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Done" <?php echo $status_filter == 'Done' ? 'selected' : ''; ?>>Done</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="sortBySelect">Sort by:</label>
                        <select id="sortBySelect" onchange="applyFilters()">
                            <option value="ticket_desc" <?php echo $sort_by === 'ticket_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="ticket_oldest" <?php echo $sort_by === 'ticket_oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="subject_asc" <?php echo $sort_by === 'subject_asc' ? 'selected' : ''; ?>>Subject (A-Z)</option>
                            <option value="subject_desc" <?php echo $sort_by === 'subject_desc' ? 'selected' : ''; ?>>Subject (Z-A)</option>
                            <option value="status_asc" <?php echo $sort_by === 'status_asc' ? 'selected' : ''; ?>>Status (A-Z)</option>
                            <option value="category_asc" <?php echo $sort_by === 'category_asc' ? 'selected' : ''; ?>>Category (A-Z)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket No.</th>
                            <th>Subject/Issue</th>
                            <th>Category</th>
                            <th>Requester (Name)</th>
                            <th>Status</th>
                            <th>Date Submitted</th>
                            <th>Date Resolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                // Format ticket number as TN0000012312
                                $ticket_no = 'TN' . str_pad($row['ticket_id'], 10, '0', STR_PAD_LEFT);
                                
                                // Format date from database
                                $transaction_date = date('M d, Y', strtotime($row['transaction_date']));
                                
                                // Format date resolved from database
                                $date_resolved = (!empty($row['date_resolved'])) ? date('M d, Y', strtotime($row['date_resolved'])) : '';
                                
                                // Map status
                                if ($row['status_'] == 'Done') {
                                    $status_class = 'status-done';
                                    $status_text = 'DONE';
                                } elseif ($row['status_'] == 'In Progress') {
                                    $status_class = 'status-in-progress';
                                    $status_text = 'IN PROGRESS';
                                } else {
                                    $status_class = 'status-pending';
                                    $status_text = 'PENDING';
                                }
                            ?>
                            <tr onclick="viewTicket(<?php echo $row['ticket_id']; ?>);" style="cursor: pointer;">
                                <td><?php echo htmlspecialchars($ticket_no); ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['subject_']); ?>">
                                    <?php 
                                        $subject = $row['subject_'];
                                        if (strlen($subject) > 20) {
                                            echo htmlspecialchars(substr($subject, 0, 20)) . '...';
                                        } else {
                                            echo htmlspecialchars($subject);
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($transaction_date); ?></td>
                                <td><?php echo $date_resolved ? htmlspecialchars($date_resolved) : '<span style="color: #9CA3AF;">-</span>'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    No tickets found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Table Footer with Pagination -->
                <div class="table-footer">
                    <div class="table-footer-left">
                        <span>Showing data</span>
                        <select class="per-page-select" onchange="changePerPage(this.value)">
                            <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>5</option>
                        </select>
                        <span>out of <?php echo $total_records; ?> entries found</span>
                    </div>
                    <div class="pagination">
                        <a href="?page=<?php echo max(1, $page - 1); ?>&per_page=<?php echo $per_page; ?>" 
                           class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            &lt;
                        </a>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?page=1&per_page=' . $per_page . '" class="pagination-btn">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            echo '<a href="?page=' . $i . '&per_page=' . $per_page . '" class="pagination-btn ' . $active . '">' . $i . '</a>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="pagination-btn disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&per_page=' . $per_page . '" class="pagination-btn">' . $total_pages . '</a>';
                        }
                        ?>
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&per_page=<?php echo $per_page; ?>" 
                           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            &gt;
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Ticket Detail Modal -->
    <div id="ticketModal" class="ticket-modal-overlay" onclick="closeTicketModal(event)">
        <div class="ticket-modal-content" onclick="event.stopPropagation()">
            <div class="ticket-modal-header">
                <h2 id="modalTicketTitle">Loading...</h2>
                <div class="ticket-modal-header-actions">
                    <button class="ticket-modal-refresh" onclick="refreshTicketData()" title="Refresh">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M3 10C3 6.68629 5.68629 4 9 4C10.5 4 11.85 4.6 12.9 5.5M17 10C17 13.3137 14.3137 16 11 16C9.5 16 8.15 15.4 7.1 14.5M7.1 14.5L4 16L5.5 12.5M12.9 5.5L16 4L14.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <button class="ticket-modal-close" onclick="closeTicketModal()" title="Close">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="ticket-modal-body">
                <div class="ticket-section">
                    <h3 class="ticket-section-title">Ticket Information</h3>
                    <div class="ticket-info-grid">
                        <div class="ticket-info-field">
                            <label>Ticket No</label>
                            <input type="text" id="modalTicketNo" readonly>
                        </div>
                        <div class="ticket-info-field">
                            <label>Subject/Issue</label>
                            <input type="text" id="modalSubject" readonly>
                        </div>
                        <div class="ticket-info-field">
                            <label>Category</label>
                            <input type="text" id="modalCategory" readonly>
                        </div>
                        <div class="ticket-info-field">
                            <label>Status</label>
                            <select id="modalStatus" class="status-select">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Done">Done</option>
                            </select>
                        </div>
                        <div class="ticket-info-field">
                            <label>Requester</label>
                            <input type="text" id="modalName" readonly>
                        </div>
                        <div class="ticket-info-field">
                            <label>Email</label>
                            <input type="text" id="modalEmail" readonly>
                        </div>
                        <div class="ticket-info-field">
                            <label>Date Submitted</label>
                            <input type="text" id="modalTransactionDate" readonly>
                        </div>
                        <div class="ticket-info-field">
                            <label>Date Resolved</label>
                            <input type="date" id="modalDateResolved" readonly>
                        </div>
                    </div>
                </div>
                <div class="ticket-section">
                    <h3 class="ticket-section-title">Problem Description</h3>
                    <div class="ticket-problem-field">
                        <label>Description</label>
                        <textarea id="modalDescription" readonly></textarea>
                    </div>
                </div>

                <div class="ticket-section" id="attachmentSection" style="display: none;">
                    <h3 class="ticket-section-title">Attachment</h3>
                    <div class="ticket-attachment-field">
                        <div id="attachmentContent" style="padding: 1rem; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB;">
                            <!-- Attachment will be displayed here -->
                        </div>
                    </div>
                </div>

                <div class="ticket-modal-actions">
                    <button class="btn-update-status" onclick="updateTicketStatus()" id="updateBtn">Update Status</button>
                    <button class="btn-cancel" onclick="closeTicketModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTicketId = null;

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification-toast notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Hide and remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        function removeFilter(type) {
            const url = new URL(window.location.href);
            if (type === 'date') url.searchParams.delete('date_filter');
            if (type === 'category') url.searchParams.delete('category_filter');
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function applyFilter(type, value) {
            const url = new URL(window.location.href);
            if (value) {
                url.searchParams.set(type + '_filter', value);
            } else {
                url.searchParams.delete(type + '_filter');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function changePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function viewTicket(ticketId) {
            currentTicketId = ticketId;
            const modal = document.getElementById('ticketModal');
            modal.classList.add('active');
            
            // Fetch ticket data
            fetch(`tickets.php?action=get_ticket&ticket_id=${ticketId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ticket = data.ticket;
                        
                        // Set modal title
                        document.getElementById('modalTicketTitle').textContent = 
                            `${ticket.category}: ${ticket.subject} (${ticket.transaction_date})`;
                        
                        // Fill in ticket information
                        document.getElementById('modalTicketNo').value = ticket.ticket_no;
                        document.getElementById('modalTransactionDate').value = ticket.transaction_date;
                        document.getElementById('modalCategory').value = ticket.category;
                        document.getElementById('modalSubject').value = ticket.subject;
                        document.getElementById('modalName').value = ticket.user_name;
                        document.getElementById('modalEmail').value = ticket.email;
                        document.getElementById('modalDescription').value = ticket.description || 'No description provided.';
                        
                        // Handle attachment display
                        const attachmentSection = document.getElementById('attachmentSection');
                        const attachmentContent = document.getElementById('attachmentContent');
                        
                        if (ticket.attachment) {
                            attachmentSection.style.display = 'block';
                            const fileExt = ticket.attachment.split('.').pop().toUpperCase();
                            const imageExtensions = ['PNG', 'JPG', 'JPEG', 'GIF', 'WEBP', 'BMP', 'SVG'];
                            const isImage = imageExtensions.includes(fileExt);
                            
                            if (isImage) {
                                // Display image preview - use view_ticket_image.php handler
                                const imagePath = `view_ticket_image.php?file=${encodeURIComponent(ticket.attachment)}`;
                                attachmentContent.innerHTML = `
                                    <div style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                                        <div style="border-radius: 8px; border: 1px solid #E5E7EB; overflow: hidden; background: #F9FAFB; display: flex; align-items: center; justify-content: center; width: 100%; min-height: 500px; max-height: 700px;">
                                            <img src="${imagePath}" style="max-width: 100%; max-height: 100%; object-fit: contain;" alt="${ticket.attachment}" onerror="this.parentElement.innerHTML='<div style=&quot;color: #9CA3AF; text-align: center; padding: 2rem;&quot;>Image not found or failed to load</div>'">
                                        </div>
                                    </div>
                                `;
                            } else {
                                // Display file download option
                                const downloadUrl = `download_attachment.php?ticket_id=${ticket.ticket_id}&file=${encodeURIComponent(ticket.attachment)}`;
                                attachmentContent.innerHTML = `
                                    <div style="display: flex; align-items: center; gap: 12px; padding: 1rem; background: white; border-radius: 6px; border: 1px solid #D1D5DB;">
                                        <div style="font-size: 2rem; color: #3B82F6;">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="7 10 12 15 17 10"></polyline>
                                                <line x1="12" y1="15" x2="12" y2="3"></line>
                                            </svg>
                                        </div>
                                        <div style="flex: 1;">
                                            <p style="margin: 0; font-weight: 600; color: #111827; font-size: 0.95rem;">${ticket.attachment}</p>
                                            <p style="margin: 4px 0 0 0; color: #6B7280; font-size: 0.875rem;">${fileExt} File</p>
                                        </div>
                                        <a href="${downloadUrl}" download class="btn-download-attachment" style="
                                            padding: 0.625rem 1.25rem;
                                            background: linear-gradient(90deg, #3B82F6 0%, #F97316 100%);
                                            color: white;
                                            border: none;
                                            border-radius: 6px;
                                            text-decoration: none;
                                            font-weight: 600;
                                            font-size: 0.875rem;
                                            cursor: pointer;
                                            transition: transform 0.2s;
                                            display: inline-block;
                                        ">
                                            Download
                                        </a>
                                    </div>
                                `;
                            }
                        } else {
                            attachmentSection.style.display = 'none';
                        }
                        
                        // Set status dropdown to the actual ticket status
                        document.getElementById('modalStatus').value = ticket.status;
                        
                        // Set Date Resolved if available
                        const dateResolvedField = document.getElementById('modalDateResolved');
                        if (ticket.date_resolved) {
                            dateResolvedField.value = ticket.date_resolved;
                        } else if (ticket.status === 'Done') {
                            // If Done but no date resolved, set current date
                            const today = new Date().toISOString().split('T')[0];
                            dateResolvedField.value = today;
                        }
                        
                        // Update date resolved field based on status
                        updateDateResolvedField(ticket.status);
                    } else {
                        alert('Error loading ticket: ' + (data.message || 'Unknown error'));
                        closeTicketModal();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading ticket details. Please try again.');
                    closeTicketModal();
                });
        }

        function updateDateResolvedField(status) {
            const dateResolvedField = document.getElementById('modalDateResolved');
            // Always keep the field readonly - don't allow manual editing
            dateResolvedField.setAttribute('readonly', true);
            
            if (status === 'Done') {
                // Auto-populate with current date when status is Done
                const today = new Date().toISOString().split('T')[0];
                dateResolvedField.value = today;
            } else {
                // Clear the field for other statuses
                dateResolvedField.value = '';
            }
        }

        // Handle status change to auto-populate date resolved
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('modalStatus');
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    updateDateResolvedField(this.value);
                });
            }
        });

        function refreshTicketData() {
            if (currentTicketId) {
                viewTicket(currentTicketId);
            }
        }

        function closeTicketModal(event) {
            if (event && event.target !== event.currentTarget) {
                return;
            }
            const modal = document.getElementById('ticketModal');
            modal.classList.remove('active');
            currentTicketId = null;
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTicketModal();
            }
        });

        function updateTicketStatus() {
            if (!currentTicketId) return;
            
            const newStatus = document.getElementById('modalStatus').value;
            const dateResolved = document.getElementById('modalDateResolved').value;
            const updateBtn = document.getElementById('updateBtn');
            
            updateBtn.disabled = true;
            updateBtn.textContent = 'Updating...';
            
            fetch(`tickets.php?action=update_ticket_status&ticket_id=${currentTicketId}&status=${encodeURIComponent(newStatus)}&date_resolved=${encodeURIComponent(dateResolved)}`)
                .then(response => {
                    // First check if response is OK
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        // Try to parse as JSON, if fails log the text
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid response from server');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        // Update the table row status immediately
                        updateTableRowStatus(currentTicketId, newStatus, dateResolved);
                        showNotification('Status updated successfully!', 'success');
                        setTimeout(() => {
                            closeTicketModal();
                        }, 500);
                    } else {
                        showNotification('Error updating status: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error updating status. Please try again. Details: ' + error.message, 'error');
                })
                .finally(() => {
                    updateBtn.disabled = false;
                    updateBtn.textContent = 'Update Status';
                });
        }

        function updateTableRowStatus(ticketId, newStatus, dateResolved) {
            // Find the row by looking at onclick attributes
            const tableRows = document.querySelectorAll('tbody tr');
            let targetRow = null;

            for (let row of tableRows) {
                const onclickAttr = row.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes(`viewTicket(${ticketId})`)) {
                    targetRow = row;
                    break;
                }
            }

            if (targetRow) {
                // Update the status badge (5th column)
                const cells = targetRow.querySelectorAll('td');
                if (cells.length >= 5) {
                    const statusCell = cells[4]; // Status is in the 5th column (index 4)
                    const statusBadge = statusCell.querySelector('.status-badge');
                    
                    if (statusBadge) {
                        // Remove old status classes
                        statusBadge.classList.remove('status-done', 'status-in-progress', 'status-pending');
                        
                        // Add new status class and text
                        if (newStatus === 'Done') {
                            statusBadge.classList.add('status-done');
                            statusBadge.textContent = 'DONE';
                        } else if (newStatus === 'In Progress') {
                            statusBadge.classList.add('status-in-progress');
                            statusBadge.textContent = 'IN PROGRESS';
                        } else {
                            statusBadge.classList.add('status-pending');
                            statusBadge.textContent = 'PENDING';
                        }
                    }
                }
                
                // Update date resolved column (7th column)
                if (cells.length >= 7) {
                    const dateResolvedCell = cells[6]; // Date Resolved is in the 7th column (index 6)
                    if (newStatus === 'Done' && dateResolved) {
                        const dateObj = new Date(dateResolved);
                        const formattedDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
                        dateResolvedCell.innerHTML = formattedDate;
                    } else {
                        // Clear date resolved for non-Done statuses
                        dateResolvedCell.innerHTML = '<span style="color: #9CA3AF;">-</span>';
                    }
                }
            }
        }

        // Apply filters and redirect
        function applyFilters() {
            const search = document.getElementById('searchInput').value.trim();
            const categoryFilter = document.getElementById('category_filter_select').value;
            const statusFilter = document.getElementById('status_filter_select').value;
            const sortBy = document.getElementById('sortBySelect').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (categoryFilter) params.append('category_filter', categoryFilter);
            if (statusFilter) params.append('status_filter', statusFilter);
            if (sortBy !== 'ticket_desc') params.append('sort_by', sortBy);

            const url = 'tickets.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const suggestions = [];
            const tableRows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            // Filter table rows based on search query
            tableRows.forEach(row => {
                if (row.querySelector('td:first-child').textContent.includes('colspan')) {
                    // Skip the "no data" row
                    return;
                }

                const ticketNo = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const subject = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const category = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const requester = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                
                const matches = ticketNo.includes(query) || subject.includes(query) || category.includes(query) || requester.includes(query);
                
                if (query === '' || matches) {
                    row.style.display = '';
                    visibleCount++;
                    if (subject.includes(query) && query !== '') {
                        suggestions.push(subject);
                    }
                } else {
                    row.style.display = 'none';
                }
            });

            // Update suggestions dropdown
            if (query.length > 0) {
                if (suggestions.length > 0) {
                    searchSuggestions.innerHTML = suggestions.map(suggestion => 
                        `<div class="suggestion-item" style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #F3F4F6; transition: background-color 0.2s;" onclick="filterBySubject('${suggestion.replace(/'/g, "\\'")}')">${suggestion}</div>`
                    ).join('');
                    searchSuggestions.style.display = 'block';
                } else {
                    if (visibleCount === 0) {
                        searchSuggestions.innerHTML = '<div style="padding: 0.75rem 1rem; color: #9CA3AF; cursor: default;">No tickets found</div>';
                        searchSuggestions.style.display = 'block';
                    } else {
                        searchSuggestions.style.display = 'none';
                    }
                }
            } else {
                searchSuggestions.style.display = 'none';
            }
        });

        // Allow search on Enter key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        function filterBySubject(subject) {
            searchInput.value = subject;
            applyFilters();
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput) {
                searchSuggestions.style.display = 'none';
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>