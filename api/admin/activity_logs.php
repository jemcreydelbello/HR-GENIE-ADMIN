<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$module_type = isset($_GET['module_type']) ? $_GET['module_type'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Build the FAQ SQL for activity logs
$sql = "SELECT al.*, a.username, a.email, a.admin_image 
        FROM ACTIVITY_LOGS al 
        LEFT JOIN admins a ON al.admin_id = a.admin_id 
        WHERE 1=1 AND NOT (al.action_ = 'CREATE' AND al.entity_type = 'Ticket')";

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " AND (a.username LIKE '%$search_escaped%' OR al.action_ LIKE '%$search_escaped%' OR al.entity_type LIKE '%$search_escaped%')";
}

if (!empty($module_type)) {
    $module_type_escaped = $conn->real_escape_string($module_type);
    $sql .= " AND al.entity_type = '$module_type_escaped'";
}

// Apply sorting
switch($sort_by) {
    case 'action_asc':
        $sql .= " ORDER BY al.action_ ASC";
        break;
    case 'action_desc':
        $sql .= " ORDER BY al.action_ DESC";
        break;
    case 'entity_type_asc':
        $sql .= " ORDER BY al.entity_type ASC, al.created_at DESC";
        break;
    case 'user_asc':
        $sql .= " ORDER BY a.username ASC, al.created_at DESC";
        break;
    case 'date_oldest':
        $sql .= " ORDER BY al.created_at ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY al.created_at DESC";
        break;
}

// Count total records before pagination
$count_sql = "SELECT COUNT(*) as total FROM ACTIVITY_LOGS al LEFT JOIN admins a ON al.admin_id = a.admin_id WHERE 1=1 AND NOT (al.action_ = 'CREATE' AND al.entity_type = 'Ticket')";if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $count_sql .= " AND (a.username LIKE '%$search_escaped%' OR al.action_ LIKE '%$search_escaped%' OR al.entity_type LIKE '%$search_escaped%')";
}
if (!empty($module_type)) {
    $module_type_escaped = $conn->real_escape_string($module_type);
    $count_sql .= " AND al.entity_type = '$module_type_escaped'";
}
$count_result = $conn->query($count_sql);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Add pagination to query
$sql .= " LIMIT $per_page OFFSET $offset";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Activity Logs</title>
    <link rel="icon" type="image/jpeg" href="assets/img/intellismart.jpg">
    <link rel="stylesheet" href="styles.css">
    <style>
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-container {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .filter-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .filter-group select {
            padding: 0.5rem 2.5rem 0.5rem 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            background-color: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23374151' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            cursor: pointer;
            transition: border-color 0.2s;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .filter-group select:hover {
            border-color: #9CA3AF;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-btn {
            padding: 0.5rem 1rem;
            background-color: #3B82F6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .search-btn:hover {
            background-color: #2563EB;
        }

        

        .results-info {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        /* Audit Trail Styles */
        .audit-trail-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .audit-trail-row:hover {
            background-color: #F9FAFB;
        }

        .audit-trail-details {
            background-color: #F9FAFB;
            padding: 1rem;
            border-radius: 8px;
            margin: 0.5rem 0;
            display: none;
        }

        .audit-trail-details.active {
            display: block;
        }

        .change-field {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #E5E7EB;
        }

        .change-field:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .change-value {
            display: flex;
            flex-direction: column;
        }

        .change-value label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .old-value {
            background-color: #FEE2E2;
            color: #991B1B;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            border-left: 3px solid #DC2626;
            font-family: monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .new-value {
            background-color: #DCFCE7;
            color: #166534;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            border-left: 3px solid #16A34A;
            font-family: monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .no-change {
            background-color: #F3F4F6;
            color: #6B7280;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-style: italic;
        }

        .expand-icon {
            display: inline-block;
            margin-left: 0.5rem;
            transition: transform 0.2s;
        }

        .expand-icon.active {
            transform: rotate(180deg);
        }

        /* Table Container Styles */
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
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

        .search-suggestions.active {
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
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="content-header">
                <h1>Activity Logs</h1>
                <p>Audit Trail - Track all changes made in the FAQ System</p>
            </div>

            <div class="filters">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search by Admin, Action, or Module..." value="<?= htmlspecialchars($search) ?>">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>
                
                <div class="filter-group">
                    <label for="moduleType">Module:</label>
                    <select id="moduleType" onchange="applyFilters()">
                        <option value="">All Modules</option>
                        <option value="Article" <?= $module_type === 'Article' ? 'selected' : '' ?>>Article</option>
                        <option value="Category" <?= $module_type === 'Category' ? 'selected' : '' ?>>Category</option>
                        <option value="Ticket" <?= $module_type === 'Ticket' ? 'selected' : '' ?>>Ticket</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="sortBy">Sort by:</label>
                    <select id="sortBy" onchange="applyFilters()">
                        <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="date_oldest" <?= $sort_by === 'date_oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="action_asc" <?= $sort_by === 'action_asc' ? 'selected' : '' ?>>Action (A-Z)</option>
                        <option value="action_desc" <?= $sort_by === 'action_desc' ? 'selected' : '' ?>>Action (Z-A)</option>
                        <option value="entity_type_asc" <?= $sort_by === 'entity_type_asc' ? 'selected' : '' ?>>Module (A-Z)</option>
                        <option value="user_asc" <?= $sort_by === 'user_asc' ? 'selected' : '' ?>>Admin (A-Z)</option>
                    </select>
                </div>

                
            </div>

            <?php 
                $total_logs = $result ? $result->num_rows : 0;
                if ($search || $module_type || $sort_by !== 'date_desc'):
            ?>
            <div class="results-info">
                Showing <?= $total_logs ?> 
                <?= $total_logs === 1 ? 'activity log' : 'activity logs' ?>
                <?php if ($search): ?>
                    matching "<?= htmlspecialchars($search) ?>"
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Date</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $counter = 0; while($row = $result->fetch_assoc()): 
                                $date = date('M d, Y H:i:s', strtotime($row['created_at']));
                                $profile_pic = !empty($row['admin_image']) ? htmlspecialchars($row['admin_image']) : 'profile.png';
                                $counter++;
                            ?>
                            <tr class="audit-trail-row" onclick="toggleDetails('details-<?= $counter ?>')">
                                <td>
                                    <div class="activity-log-profile">
                                        <img src="<?= $profile_pic ?>" alt="<?= htmlspecialchars($row['username']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['username'] ?? 'Unknown') ?></td>
                                <td><span style="background-color: #EFF6FF; color: #1E40AF; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($row['action_']) ?></span></td>
                                <td><span style="background-color: #F0FDF4; color: #166534; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($row['entity_type'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($date) ?></td>
                                <td style="text-align: center;">
                                    <?php if (!empty($row['old_value']) || !empty($row['new_value'])): ?>
                                        <svg class="expand-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    <?php else: ?>
                                        <span style="color: #D1D5DB;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($row['old_value']) || !empty($row['new_value'])): ?>
                            <tr>
                                <td colspan="6" style="padding: 0;">
                                    <div id="details-<?= $counter ?>" class="audit-trail-details">
                                        <div style="padding: 1rem;">
                                            <h4 style="margin: 0 0 1rem 0; color: #1F2937; font-size: 0.875rem; font-weight: 600; text-transform: uppercase;">Change Details</h4>
                                            <div class="change-field">
                                                <div class="change-value">
                                                    <label>Old Value</label>
                                                    <?php if (!empty($row['old_value'])): ?>
                                                        <div class="old-value"><?= htmlspecialchars($row['old_value']) ?></div>
                                                    <?php else: ?>
                                                        <div class="no-change">No previous value</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="change-value">
                                                    <label>New Value</label>
                                                    <?php if (!empty($row['new_value'])): ?>
                                                        <div class="new-value"><?= htmlspecialchars($row['new_value']) ?></div>
                                                    <?php else: ?>
                                                        <div class="no-change">No new value</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">
                                    No activity logs found.
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

    <script>
        // Toggle details view
        function toggleDetails(id) {
            const element = document.getElementById(id);
            if (element) {
                element.classList.toggle('active');
                // Rotate the expand icon
                const row = event.currentTarget;
                const icon = row.querySelector('.expand-icon');
                if (icon) {
                    icon.classList.toggle('active');
                }
            }
        }

        // Apply filters and redirect
        function applyFilters() {
            const search = document.getElementById('searchInput').value.trim();
            const moduleType = document.getElementById('moduleType').value;
            const sortBy = document.getElementById('sortBy').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (moduleType) params.append('module_type', moduleType);
            if (sortBy !== 'date_desc') params.append('sort_by', sortBy);

            const url = 'activity_logs.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

        // Search suggestions functionality
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        const auditTrailRows = document.querySelectorAll('.audit-trail-row');

        // Live search functionality
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const suggestions = [];
            const suggestionSet = new Set(); // To avoid duplicates

            if (query.length > 0) {
                // Filter table rows and collect all matching suggestions
                auditTrailRows.forEach(row => {
                    const userName = row.querySelector('td:nth-child(2)').textContent.trim();
                    const userNameLower = userName.toLowerCase();
                    const action = row.querySelector('td:nth-child(3)').textContent.trim();
                    const actionLower = action.toLowerCase();
                    const module = row.querySelector('td:nth-child(4)').textContent.trim();
                    const moduleLower = module.toLowerCase();
                    
                    if (userNameLower.includes(query) || actionLower.includes(query) || moduleLower.includes(query)) {
                        row.style.display = '';
                        
                        // Add unique suggestions from all three columns
                        if (userNameLower.includes(query) && !suggestionSet.has(userName)) {
                            suggestionSet.add(userName);
                            suggestions.push(userName);
                        }
                        if (actionLower.includes(query) && !suggestionSet.has(action)) {
                            suggestionSet.add(action);
                            suggestions.push(action);
                        }
                        if (moduleLower.includes(query) && !suggestionSet.has(module)) {
                            suggestionSet.add(module);
                            suggestions.push(module);
                        }
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Show suggestions dropdown
                if (suggestions.length > 0) {
                    searchSuggestions.innerHTML = suggestions.map(suggestion => 
                        `<div class="suggestion-item" onclick="filterBySearch('${suggestion.replace(/'/g, "\\'")}')"> ${suggestion}</div>`
                    ).join('');
                    searchSuggestions.classList.add('active');
                } else {
                    searchSuggestions.innerHTML = '<div class="suggestion-item no-results" style="color: #9CA3AF; cursor: default;">No results found</div>';
                    searchSuggestions.classList.add('active');
                }
            } else {
                // Show all rows if search is empty
                auditTrailRows.forEach(row => {
                    row.style.display = '';
                });
                searchSuggestions.classList.remove('active');
            }
        });

        // Click on suggestion to filter
        function filterBySearch(value) {
            searchInput.value = value;
            const event = new Event('input', { bubbles: true });
            searchInput.dispatchEvent(event);
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && !searchSuggestions.contains(e.target)) {
                searchSuggestions.classList.remove('active');
            }
        });

        // Keyboard navigation for suggestions
        let currentSuggestionIndex = -1;
        searchInput.addEventListener('keydown', function(e) {
            const suggestionItems = searchSuggestions.querySelectorAll('.suggestion-item:not(.no-results)');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentSuggestionIndex = (currentSuggestionIndex + 1) % suggestionItems.length;
                updateSuggestionHighlight(suggestionItems);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentSuggestionIndex = (currentSuggestionIndex - 1 + suggestionItems.length) % suggestionItems.length;
                updateSuggestionHighlight(suggestionItems);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentSuggestionIndex >= 0 && currentSuggestionIndex < suggestionItems.length) {
                    suggestionItems[currentSuggestionIndex].click();
                } else if (searchInput.value.trim()) {
                    // Search on Enter if no suggestion selected
                    applyFilters();
                }
            } else if (e.key === 'Escape') {
                searchSuggestions.classList.remove('active');
                currentSuggestionIndex = -1;
            }
        });

        function updateSuggestionHighlight(suggestionItems) {
            suggestionItems.forEach((item, index) => {
                if (index === currentSuggestionIndex) {
                    item.style.backgroundColor = '#F0F9FF';
                    item.style.borderLeft = '3px solid #3B82F6';
                    item.style.paddingLeft = 'calc(1rem - 3px)';
                } else {
                    item.style.backgroundColor = '';
                    item.style.borderLeft = '';
                    item.style.paddingLeft = '';
                }
            });
        }

        // Reset suggestion index on input
        searchInput.addEventListener('input', function() {
            currentSuggestionIndex = -1;
        });

        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && currentSuggestionIndex === -1) {
                applyFilters();
            }
        });

        // Change per-page items
        function changePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1'); // Reset to first page
            window.location.search = params.toString();
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
