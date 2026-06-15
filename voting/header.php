<?php
// header.php - Common header for all pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .navbar { background: rgba(0,0,0,0.2); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; backdrop-filter: blur(10px); }
        .navbar .title h1 { margin: 0; font-size: 1.5rem; }
        .navbar .links { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .navbar a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; transition: background-color 0.3s; }
        .navbar a:hover, .navbar a.active { background-color: rgba(255,255,255,0.2); }
        
        /* Help button special styling */
        .help-link {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .help-link:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) { 
            .navbar { flex-direction: column; text-align: center; gap: 10px; } 
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="title"><h1><i class="fas fa-vote-yea"></i> Witty Voting Management System</h1></div>
            <div class="links">
                <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="vote.php"><i class="fas fa-check-circle"></i> Vote</a>
                <a href="apply.php"><i class="fas fa-user-plus"></i> Candidacy Appli.</a>
                <a href="contest.php"><i class="fas fa-users"></i> Contesters</a>
                <a href="my_applications.php"><i class="fas fa-file-alt"></i> My Apps</a>
                <a href="index.php"><i class="fas fa-chart-bar"></i> Results</a>
                <a href="help.php" class="help-link"><i class="fas fa-question-circle"></i> Help</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>
