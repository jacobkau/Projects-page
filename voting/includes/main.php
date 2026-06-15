<?php
include("conn.php");

// Admin Authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Determine which content to load
$page = isset($_GET['page']) ? $_GET['page'] : 'home';


// Determine the current page for active link
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <meta name="description" content="An online Voting Management System.">
    <meta name="keywords" content="portfolio, projects, web development, design">
    <meta name="author" content="Jacob witty">
    <link rel="icon" href="../logo.jpg" type="image/x-icon">
    <style>
        /* (Your inline CSS from the previous response) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f7f9;
            color: #333;
        }

        header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: left;
        }

        header h1 {
            margin: 0;
            font-size: 1.8em;
        }

        .container {
            display: flex;
            flex-grow: 1;
        }

        .sidebar {
            background-color: #e9ecef;
            width: 250px;
            padding: 20px;
            box-sizing: border-box;
            border-right: 1px solid #ddd;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar li {
            margin-bottom: 10px;
        }

        .sidebar a {
            display: block;
            padding: 12px 15px;
            text-decoration: none;
            color: #333;
            border-radius: 5px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .sidebar a:hover, .sidebar a.active {
            background-color: #007bff;
            color: white;
        }

        main {
            flex-grow: 1;
            padding: 30px;
            box-sizing: border-box;
        }

        .main-content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #007bff;
            margin-bottom: 20px;
        }

        p {
            line-height: 1.6;
            color: #555;
        }
        .sidebar a.active {
            background-color: #007bff; /* Or your preferred active color */
            color: white;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
   <header>
    <h1>Admin Panel</h1>
    <nav style="float: right;">
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="display: inline-block; margin-left: 45px;">
                <a href="logout.php" style="text-decoration: none; color: #ffffff;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
    <div style="clear: both;"></div>
</header>
    <div class="container">
        <aside class="sidebar">
            <ul>
              <li><a href="main.php?page=home" <?php if ($currentPage === 'home') echo 'class="active"'; ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
              <li><a href="main.php?page=users" <?php if ($currentPage === 'users') echo 'class="active"'; ?>><i class="fas fa-users"></i> Manage Voters</a></li>
              <li><a href="main.php?page=votes" <?php if ($currentPage === 'votes') echo 'class="active"'; ?>><i class="fas fa-vote-yea"></i> Manage Votes</a></li>
              <li><a href="main.php?page=candidates" <?php if ($currentPage === 'candidates') echo 'class="active"'; ?>><i class="fas fa-user-tie"></i> Manage Candidates</a></li>
              <li><a href="main.php?page=elections" <?php if ($currentPage === 'elections') echo 'class="active"'; ?>><i class="fas fa-calendar-alt"></i> Manage Elections</a></li>
              <li><a href="main.php?page=posts" <?php if ($currentPage === 'posts') echo 'class="active"'; ?>><i class="fas fa-clipboard-list"></i> Manage Posts</a></li>
              <li><a href="main.php?page=settings" <?php if ($currentPage === 'settings') echo 'class="active"'; ?>><i class="fas fa-cogs"></i> Voting Settings</a></li>
              <li><a href="main.php?page=results" <?php if ($currentPage === 'results') echo 'class="active"'; ?>><i class="fas fa-chart-bar"></i> View Results</a></li>
            <li><a href="main.php?page=profile" <?php if ($currentPage === 'profile') echo 'class="active"'; ?>><i class="fas fa-user-cog"></i>Profile settings</a></li>
                <br>
            <li><a style="color:red;font-weight:500" href="main.php?page=refreshvotes" <?php if ($currentPage === 'refreshvotes') echo 'class="active"'; ?>><i class="fas fa-tachometer-alt"></i> Refresh Vote</a></li>
            </ul>
        </aside>
        <main>
            <div class="main-content">
                <?php
// Include the appropriate content file
switch ($page) {
    case 'users':
        include("manage_users.php");
        break;
    case 'votes':
        include("manage_votes.php");
        break;
    case 'candidates':
        include("manage_candidates.php");
        break;
    case 'elections':
        include("manage_elections.php");
        break;
        case 'posts':
        include("admin_manage_posts.php");
        break;
    case 'settings':
        include("voting_settings.php");
        break;
    case 'results':
        include("view_results.php");
        break;
     case 'profile':
        include("admin_settings.php");
        break;    
    case 'refreshdb':
        include("refreshdb.php");
        break;
    case 'home':
    default:
        include("admin.php");
        break;
}// The included content will be displayed here. No need for $currentPage here.
                ?>
            </div>
        </main>
    </div>
</body>
</html>
