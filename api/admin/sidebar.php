<?php
// Get database connection and current user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    include 'db.php';
}

// Fetch current user data
$current_user = null;
if (isset($_SESSION['admin_id'])) {
    $user_result = $conn->query("SELECT * FROM admins WHERE admin_id = " . $_SESSION['admin_id']);
    $current_user = $user_result ? $user_result->fetch_assoc() : null;
    
    // Fallback to session variables if database is empty
    if (!$current_user) {
        $current_user = [
            'admin_id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_name'] ?? 'Admin User',
            'email' => $_SESSION['admin_email'] ?? 'admin@company.com',
            'profile_picture' => $_SESSION['admin_profile_picture'] ?? 'profile.png'
        ];
    }
}

// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$pages = [
    'dashboard.php' => 'Dashboard',
    'category.php' => 'Categories',
    'tags.php' => 'Tags',
    'articles.php' => 'Articles',
    'tickets.php' => 'Tickets',
    'activity_logs.php' => 'Activity Logs',
    'users.php' => 'Users',
    'settings.php' => 'Settings'
];

// Check if viewing articles filtered by subcategory, highlight Categories instead
$is_category_section = $current_page === 'category.php' || 
                       ($current_page === 'subcategories.php');
?>

<style>
    @font-face {
        font-family: 'Yaro Op Black';
        src: url('assets/font/yaro-op-black.woff2') format('woff2'),
             url('assets/font/yaro-op-black.woff') format('woff');
        font-weight: 900;
        font-style: normal;
        font-display: swap;
    }

    .sidebar-nav li.active svg {
        stroke: white !important;
    }
    .sidebar-nav li.active svg path {
        stroke: white !important;
    }
    .sidebar-nav li.active svg g {
        stroke: white !important;
    }
    .sidebar-nav li.active svg circle {
        stroke: white !important;
    }
    .sidebar-nav li.active svg rect {
        stroke: white !important;
    }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <div style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 1.5rem;">
            <img src="assets/img/hr2.png" alt="HR Logo" style="height: 35px; object-fit: contain;">
            <span style="font-family: 'Yaro Op Black', sans-serif; color: #00AEEF; font-size: 1.4rem; margin-top: 3.5px; margin-left: 0px; white-space: nowrap;">H<span style="font-family: 'Yaro Op Black', sans-serif; color: #f07e32;">R</span></span>
            <span style="font-family: 'Yaro Op Black', sans-serif; color: #00AEEF; font-size: 1.4rem; margin-top: 3.5px; margin-left: 0px; white-space: nowrap;">Ge<span style="font-family: 'Yaro Op Black', sans-serif; color: #f07e32;">nie</span></span>
        </div>
    </div>

    
    <div class="user-profile">
        <div class="profile-picture">
            <img src="<?php echo htmlspecialchars($current_user['admin_image'] ?? $current_user['profile_picture'] ?? 'profile.png'); ?>" alt="Profile">
        </div>
        <div class="user-info">
            <span class="user-role">ADMIN</span>
            <span class="user-name"><?php echo htmlspecialchars($current_user['full_name'] ?? 'Admin User'); ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <h3 class="nav-section">MAIN</h3>
        <ul>
            <li <?php echo $current_page === 'dashboard.php' ? 'class="active"' : ''; ?>>
                <a href="dashboard.php">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <rect x="2" y="2" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="11" y="2" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="2" y="11" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="11" y="11" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li <?php echo $is_category_section ? 'class="active"' : ''; ?>>
                <a href="category.php">
                   <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="#484847" stroke-width="1.5"><path stroke-linecap="round" d="M18 10h-5" opacity="0.5"/><path d="M2 6.95c0-.883 0-1.324.07-1.692A4 4 0 0 1 5.257 2.07C5.626 2 6.068 2 6.95 2c.386 0 .58 0 .766.017a4 4 0 0 1 2.18.904c.144.119.28.255.554.529L11 4c.816.816 1.224 1.224 1.712 1.495a4 4 0 0 0 .848.352C14.098 6 14.675 6 15.828 6h.374c2.632 0 3.949 0 4.804.77q.119.105.224.224c.77.855.77 2.172.77 4.804V14c0 3.771 0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14z"/></g></svg>
                    <span>Categories</span>
                </a>
            </li>
             <li <?php echo $current_page === 'tags.php' ? 'class="active"' : ''; ?>>
                <a href="tags.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="#484847" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M13.172 2a2 2 0 0 1 1.414.586l6.71 6.71a2.4 2.4 0 0 1 0 3.408l-4.592 4.592a2.4 2.4 0 0 1-3.408 0l-6.71-6.71A2 2 0 0 1 6 9.172V3a1 1 0 0 1 1-1zM2 7v6.172a2 2 0 0 0 .586 1.414l6.71 6.71a2.4 2.4 0 0 0 3.191.193"/><circle cx="10.5" cy="6.5" r=".5" fill="#f4f3f1"/></g></svg>
                    <span>Tags</span>
                </a>
            </li>
            <li <?php echo $current_page === 'articles.php' ? 'class="active"' : ''; ?>>
                <a href="articles.php">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M4 4C4 2.89543 4.89543 2 6 2H14C15.1046 2 16 2.89543 16 4V16C16 17.1046 15.1046 18 14 18H6C4.89543 18 4 17.1046 4 16V4Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M7 6H13M7 10H13M7 14H10" stroke="#484847" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>Articles</span>
                </a>
            </li>
            <li <?php echo $current_page === 'tickets.php' ? 'class="active"' : ''; ?>>
                <a href="tickets.php">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M4 4H16C17.1046 4 18 4.89543 18 6V9C17.4477 9 17 9.44772 17 10C17 10.5523 17.4477 11 18 11V14C18 15.1046 17.1046 16 16 16H4C2.89543 16 2 15.1046 2 14V11C2.55228 11 3 10.5523 3 10C3 9.44772 2.55228 9 2 9V6C2 4.89543 2.89543 4 4 4Z" stroke="#484847" stroke-width="1.5"/>
                    </svg>
                    <span>Tickets</span>
                </a>
            </li>
            <li <?php echo $current_page === 'activity_logs.php' ? 'class="active"' : ''; ?>>
                <a href="activity_logs.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="#484847" stroke-linecap="round" stroke-width="1.5"><path stroke-linejoin="round" d="M19 10.5V10c0-3.771 0-5.657-1.172-6.828S14.771 2 11 2S5.343 2 4.172 3.172S3 6.229 3 10v6c0 1.864 0 2.796.304 3.53a4 4 0 0 0 2.165 2.165C6.204 22 7.136 22 9 22"/><path d="M7 7h8m-8 4h4"/><path stroke-linejoin="round" d="M15.283 19.004c-.06-.888-.165-1.838-.601-2.912c-.373-.916-.269-3.071 1.818-3.071s2.166 2.155 1.794 3.07c-.436 1.075-.518 2.025-.576 2.913M21 22h-9v-1.246c0-.446.266-.839.653-.961l2.255-.716q.242-.077.494-.077h2.196q.252 0 .494.077l2.255.716c.387.122.653.515.653.961z"/></g></svg>
                    <span>Activity Logs</span>
                </a>
            </li>
            <li <?php echo $current_page === 'users.php' ? 'class="active"' : ''; ?>>
                <a href="users.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="#484847" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-2.618 2.686-4.5 6-4.5s6 1.882 6 4.5"/><path d="M17 20c0-2.618 2.686-4.5 6-4.5s6 1.882 6 4.5"/><circle cx="19" cy="8" r="3"/></g></svg>
                    <span>Users</span>
                </a>
            </li>
             <li <?php echo $current_page === 'settings.php' ? 'class="active"' : ''; ?>>
                <a href="settings.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="#484847" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M12.132 15.404a3.364 3.364 0 1 0 0-6.728a3.364 3.364 0 0 0 0 6.728"/><path d="M20.983 15.094a9.4 9.4 0 0 1-1.802 3.1l-2.124-.482a7.25 7.25 0 0 1-2.801 1.56l-.574 2.079a9.5 9.5 0 0 1-1.63.149a9 9 0 0 1-2.032-.23l-.609-2.146a7.5 7.5 0 0 1-2.457-1.493l-2.1.54a9.4 9.4 0 0 1-1.837-3.33l1.55-1.722a7.2 7.2 0 0 1 .069-2.652L3.107 8.872a9.4 9.4 0 0 1 2.067-3.353l2.17.54A7.7 7.7 0 0 1 9.319 4.91l.574-2.124a9 9 0 0 1 2.17-.287c.585 0 1.17.054 1.745.16l.551 2.113c.83.269 1.608.68 2.296 1.217l2.182-.563a9.4 9.4 0 0 1 2.043 3.1l-1.48 1.607a7.4 7.4 0 0 1 .068 3.364z"/></g></svg>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <a href="logout.php" class="logout-btn" style="text-decoration: none; font-weight: bold;">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M13 6L17 10M17 10L13 14M17 10H7M11 18H5C3.89543 18 3 17.1046 3 16V4C3 2.89543 3.89543 2 5 2H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>Logout</span>
    </a>
</aside>
