<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'add_tag') {
        $tag_name = $_POST['tag_name'] ?? '';
        
        if (!empty($tag_name)) {
            $tag_name = $conn->real_escape_string($tag_name);
            
            // Check if tag already exists
            $check_sql = "SELECT tag_id FROM tags WHERE tag_name = '$tag_name'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result && $check_result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Tag already exists']);
                exit();
            }
            
            $sql = "INSERT INTO tags (tag_name) VALUES ('$tag_name')";
            
            if ($conn->query($sql)) {
                $tag_id = $conn->insert_id;
                
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $log_sql = "INSERT INTO ACTIVITY_LOGS (user_id, action_, entity_type, entity_id, new_value) 
                            VALUES ($admin_id, 'CREATE', 'Tag', $tag_id, '$tag_name')";
                $conn->query($log_sql);
                
                echo json_encode(['success' => true, 'message' => 'Tag created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating tag: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Tag name is required']);
        }
        exit();
    } elseif ($action === 'delete_tag') {
        $tag_id = intval($_POST['tag_id'] ?? 0);
        
        if ($tag_id > 0) {
            // Get tag name for logging
            $tag_sql = "SELECT tag_name FROM tags WHERE tag_id = $tag_id LIMIT 1";
            $tag_result = $conn->query($tag_sql);
            $tag_data = $tag_result ? $tag_result->fetch_assoc() : null;
            $tag_name = $tag_data ? $tag_data['tag_name'] : 'Unknown';
            
            // Delete the tag from tags table (cascade deletes from article_tags)
            $sql = "DELETE FROM tags WHERE tag_id = $tag_id";
            
            if ($conn->query($sql)) {
                // Log the activity
                $admin_id = $_SESSION['admin_id'];
                $tag_name_escaped = $conn->real_escape_string($tag_name);
                $log_sql = "INSERT INTO ACTIVITY_LOGS (user_id, action_, entity_type, entity_id, old_value) 
                            VALUES ($admin_id, 'DELETE', 'Tag', $tag_id, '$tag_name_escaped')";
                $conn->query($log_sql);
                
                echo json_encode(['success' => true, 'message' => 'Tag deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting tag: ' . $conn->error]);
            }
        }
        exit();
    }
}

$message = '';
$message_type = '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';

// Pagination settings
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Fetch all tags with article count
$tags_sql = "SELECT 
                t.tag_id,
                t.tag_name,
                COUNT(DISTINCT at.article_id) as usage_count
            FROM tags t
            LEFT JOIN article_tags at ON t.tag_id = at.tag_id
            WHERE 1=1";

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $tags_sql .= " AND t.tag_name LIKE '%$search_escaped%'";
}

$tags_sql .= " GROUP BY t.tag_id, t.tag_name";

// Apply sorting
switch($sort_by) {
    case 'name_asc':
        $tags_sql .= " ORDER BY t.tag_name ASC";
        break;
    case 'name_desc':
        $tags_sql .= " ORDER BY t.tag_name DESC";
        break;
    case 'usage_high':
        $tags_sql .= " ORDER BY usage_count DESC, t.tag_name ASC";
        break;
    case 'usage_low':
        $tags_sql .= " ORDER BY usage_count ASC, t.tag_name ASC";
        break;
    case 'date_oldest':
        $tags_sql .= " ORDER BY t.tag_name ASC";
        break;
    case 'date_desc':
    default:
        $tags_sql .= " ORDER BY t.tag_name ASC";
        break;
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM ($tags_sql) as count_table";
$count_result = $conn->query($count_sql);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Add pagination to query
$tags_sql .= " LIMIT $per_page OFFSET $offset";

$tags_result = $conn->query($tags_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Genie | Tags</title>
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

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #E5E7EB;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .search-suggestions.active {
            display: block;
        }

        .suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #F3F4F6;
            transition: background-color 0.2s;
        }

        .suggestion-item:hover {
            background-color: #F9FAFB;
        }

        .suggestion-item.no-results {
            color: #9CA3AF;
            cursor: default;
        }

        .suggestion-item.no-results:hover {
            background-color: white;
        }

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
        }

        .filter-group select {
            padding: 0.625rem 2.5rem 0.625rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            background-color: #FFFFFF;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23374151' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .filter-group select:hover {
            border-color: #9CA3AF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filter-group select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .results-info {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        /* Pagination Styles */
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E5E7EB;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-footer-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: #6B7280;
        }

        .per-page-select {
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

        .per-page-select:hover {
            border-color: #9CA3AF;
        }

        .per-page-select:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            background-color: white;
            color: #374151;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background-color: #F3F4F6;
            border-color: #9CA3AF;
        }

        .pagination-btn.active {
            background-color: #3B82F6;
            color: white;
            border-color: #3B82F6;
        }

        .pagination-btn.disabled {
            color: #D1D5DB;
            cursor: not-allowed;
            background-color: #F9FAFB;
            border-color: #E5E7EB;
        }

        /* Tags Container Styles */
        .tags-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .tag-input-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 2rem;
        }

        .tag-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .tag-input:focus {
            outline: none;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .tag-btn-add {
            padding: 0.75rem 1.5rem;
            background: #3B82F6;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tag-btn-add:hover {
            background: #2563EB;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }

        .tag-btn-add:active {
            transform: scale(0.98);
        }

        .tags-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
        }

        .tag-card {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .tag-card:hover {
            background: #F3F4F6;
            border-color: #D1D5DB;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .tag-card-info {
            flex: 1;
            min-width: 0;
        }

        .tag-name {
            font-weight: 600;
            color: #1F2937;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .tag-meta {
            font-size: 0.75rem;
            color: #9CA3AF;
            margin-top: 0.5rem;
        }

        .tag-badge {
            background: #DBEAFE;
            color: #1E40AF;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .tag-btn-delete {
            padding: 0.5rem 0.75rem;
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tag-btn-delete:hover:not(:disabled) {
            background: #FECACA;
        }

        .tag-btn-delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #F3F4F6;
            color: #9CA3AF;
            border-color: #E5E7EB;
        }

        .tags-empty {
            text-align: center;
            padding: 3rem;
            color: #9CA3AF;
            grid-column: 1 / -1;
        }

        .tags-empty svg {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .tags-list {
                grid-template-columns: 1fr;
            }

            .tag-input-group {
                flex-direction: column;
            }

            .tag-btn-add {
                width: 100%;
            }

            .tag-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .tag-btn-delete {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <div class="content-header">
                <h1>Tags</h1>
                <p>Manage tags for articles</p>
            </div>

            <div class="filters">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search tags..." value="<?= htmlspecialchars($search) ?>">
                    <div id="searchSuggestions" class="search-suggestions"></div>
                </div>
                
                <div class="filter-group">
                    <label for="sortBy">Sort by:</label>
                    <select id="sortBy" onchange="applyFilters()">
                        <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Newest</option>
                        <option value="date_oldest" <?= $sort_by === 'date_oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="usage_high" <?= $sort_by === 'usage_high' ? 'selected' : '' ?>>Most Used</option>
                        <option value="usage_low" <?= $sort_by === 'usage_low' ? 'selected' : '' ?>>Least Used</option>
                    </select>
                </div>
            </div>

            <div class="tags-container">
                <div class="tag-input-group">
                    <input type="text" id="newTagInput" placeholder="Create new tag..." class="tag-input">
                    <button type="button" class="tag-btn-add" onclick="addNewTag()">+ Add Tag</button>
                </div>

                <?php 
                    $total_tags = $tags_result ? $tags_result->num_rows : 0;
                    if ($search || $sort_by !== 'date_desc'):
                ?>
                <div class="results-info">
                    Showing <?= $total_tags ?> 
                    <?= $total_tags === 1 ? 'tag' : 'tags' ?>
                    <?php if ($search): ?>
                        matching "<?= htmlspecialchars($search) ?>"
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="tags-list" id="tagsList">
                    <?php if ($tags_result && $tags_result->num_rows > 0): ?>
                        <?php while($row = $tags_result->fetch_assoc()): ?>
                        <div class="tag-card">
                            <div class="tag-card-info">
                                <div class="tag-name"><?= htmlspecialchars($row['tag_name']) ?></div>
                                <?php if ($row['usage_count'] > 0): ?>
                                    <span class="tag-badge"><?= $row['usage_count'] ?> article<?= $row['usage_count'] !== 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="tag-btn-delete" onclick="deleteTag(<?= $row['tag_id'] ?>, '<?= htmlspecialchars(addslashes($row['tag_name'])) ?>', <?= $row['usage_count'] ?>)">Delete</button>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="tags-empty">
                            <p>No tags found. Create your first tag to get started!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Table Footer with Pagination -->
                <div class="table-footer">
                    <div class="table-footer-left">
                        <span>Showing data</span>
                        <select class="per-page-select" onchange="changePerPage(this.value)">
                            <option value="12" <?php echo $per_page == 12 ? 'selected' : ''; ?>>12</option>
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
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');

        // Apply filters and redirect
        function applyFilters() {
            const search = searchInput.value.trim();
            const sortBy = document.getElementById('sortBy').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (sortBy !== 'date_desc') params.append('sort_by', sortBy);

            const url = 'tags.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

        // Live search functionality
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const suggestions = [];

            if (query.length > 0) {
                // Filter tag cards
                document.querySelectorAll('.tag-card').forEach(card => {
                    const tagName = card.querySelector('.tag-name').textContent.toLowerCase();
                    
                    if (tagName.includes(query)) {
                        card.style.display = '';
                        suggestions.push(tagName.trim());
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Show suggestions dropdown
                if (suggestions.length > 0) {
                    searchSuggestions.innerHTML = suggestions.slice(0, 5).map(suggestion => 
                        `<div class="suggestion-item" onclick="filterByTag('${suggestion}')">${suggestion}</div>`
                    ).join('');
                    searchSuggestions.classList.add('active');
                } else {
                    searchSuggestions.innerHTML = '<div class="suggestion-item no-results">No tags found</div>';
                    searchSuggestions.classList.add('active');
                }
            } else {
                // Show all cards if search is empty
                document.querySelectorAll('.tag-card').forEach(card => {
                    card.style.display = '';
                });
                searchSuggestions.classList.remove('active');
            }
        });

        // Click on suggestion to filter
        function filterByTag(tagName) {
            searchInput.value = tagName;
            applyFilters();
        }

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== searchInput && e.target.parentElement !== searchSuggestions) {
                searchSuggestions.classList.remove('active');
            }
        });

        // Show notification
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

        // Add new tag
        function addNewTag() {
            const input = document.getElementById('newTagInput');
            const tagName = input.value.trim();

            if (!tagName) {
                showNotification('Please enter a tag name', 'error');
                input.focus();
                return;
            }

            if (tagName.length > 50) {
                showNotification('Tag name must be 50 characters or less', 'error');
                return;
            }

            fetch('tags.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=add_tag&tag_name=' + encodeURIComponent(tagName)
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showNotification('Tag created successfully!', 'success');
                        input.value = '';
                        input.focus();
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        showNotification(data.message || 'Error creating tag', 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    // Still reload on success even if there's a parse error
                    showNotification('Tag created successfully!', 'success');
                    input.value = '';
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Don't show error notification on catch
            });
        }

        // Delete tag
        function deleteTag(tagId, tagName, usageCount) {
            // Show custom confirmation modal
            const modal = document.getElementById('tagDeleteConfirmModal');
            let message = `Are you sure you want to delete "${tagName}"? This action cannot be undone.`;
            if (usageCount > 0) {
                message += ` This tag is associated with ${usageCount} article${usageCount !== 1 ? 's' : ''}.`;
            }
            document.getElementById('tagDeleteMessage').textContent = message;
            modal.style.display = 'flex';
            
            // Handle cancel button
            const cancelBtn = document.getElementById('tagDeleteCancel');
            cancelBtn.onclick = function() {
                modal.style.display = 'none';
            };
            
            // Handle confirm delete button
            const confirmBtn = document.getElementById('tagDeleteConfirm');
            confirmBtn.onclick = function() {
                modal.style.display = 'none';
                // Proceed with deletion
                fetch('tags.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_tag&tag_id=' + tagId
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showNotification('Tag deleted successfully', 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 800);
                        } else {
                            showNotification(data.message || 'Error deleting tag', 'error');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        // Still reload on success even if there's a parse error
                        showNotification('Tag deleted successfully', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Don't show error notification on catch
                });
            };
            
            // Close modal on clicking outside
            modal.onclick = function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            };
        }

        // Change per-page items
        function changePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1');
            window.location.search = params.toString();
        }

        // Check for status parameter on page load
        window.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const status = params.get('status');
            const message = params.get('message');

            if (status && message) {
                showNotification(decodeURIComponent(message), status);
                window.history.replaceState({}, document.title, 'tags.php');
            }

            // Allow Enter key to add tag
            document.getElementById('newTagInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    addNewTag();
                }
            });
        });
    </script>

    <!-- Delete Confirmation Modal for Tags -->
    <div id="tagDeleteConfirmModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 400px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease;">
            <div style="margin-bottom: 1.5rem;">
                <div style="width: 60px; height: 60px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 0.5rem 0;">Delete Tag?</h3>
                <p id="tagDeleteMessage" style="font-size: 0.875rem; color: #6B7280; margin: 0;">Are you sure you want to delete this tag? This action cannot be undone.</p>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button id="tagDeleteCancel" type="button" style="padding: 0.75rem 1.5rem; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Cancel
                </button>
                <button id="tagDeleteConfirm" type="button" style="padding: 0.75rem 1.5rem; background: #DC2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.875rem;">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</body>
</html>

<?php $conn->close(); ?>
