<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel</title>
    <link rel="icon" type="image/png" href="tab-logo.png?v=3">
    <link rel="apple-touch-icon" href="tab-logo.png?v=3">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
       :root {
            --bg: #090e17; 
            --card: rgba(30, 41, 59, 0.4); 
            --text: #f8fafc; 
            --text-muted: #94a3b8; 
            --accent: #0ea5e9; 
            --accent-glow: rgba(14, 165, 233, 0.4);
            --border: rgba(255, 255, 255, 0.08);
            --danger: #ef4444;
            --success: #10b981;
            --smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body { 
            margin: 0; font-family: 'Inter', sans-serif; color: var(--text); 
            background-color: var(--bg); 
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(14, 165, 233, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(99, 102, 241, 0.08), transparent 25%);
            background-attachment: fixed;
            display: flex; height: 100vh; overflow: hidden; 
        }

        /* Sidebar */
        .sidebar { 
            width: 260px; 
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid var(--border); 
            padding: 24px; display: flex; flex-direction: column; 
            transition: var(--smooth);
        }
        .brand { font-size: 20px; font-weight: 700; margin-bottom: 30px; color: #fff; letter-spacing: 0.5px; }
        .brand::before { content: '⚙️ '; } 

        .nav-item { padding: 14px 16px; margin-bottom: 8px; cursor: pointer; border-radius: 12px; transition: var(--smooth); color: var(--text-muted); font-size: 14px; font-weight: 500; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; transform: translateX(4px); }
        .nav-item.active { background: rgba(14, 165, 233, 0.1); color: var(--accent); box-shadow: inset 3px 0 0 var(--accent); }

        .back-btn { 
            margin-top: auto; color: var(--danger); background: rgba(239, 68, 68, 0.1); 
            border: 1px solid rgba(239, 68, 68, 0.3); text-align: center; padding: 14px; 
            border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 13px; 
            transition: var(--smooth); letter-spacing: 0.5px;
        }
        .back-btn:hover { background: rgba(239, 68, 68, 0.2); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }

        /* Main Content */
        .main { flex: 1; padding: 40px; overflow-y: auto; scroll-behavior: smooth; }
        .section { display: none; animation: fadeIn 0.4s ease-out; }
        .section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        h2 { font-size: 28px; font-weight: 700; margin-top: 0; margin-bottom: 24px; letter-spacing: -0.5px; }
        h3 { font-size: 16px; margin-top: 0; color: #fff; margin-bottom: 20px; }

        /* Cards & Tables */
        .card { 
            background: var(--card); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); 
            padding: 32px; border-radius: 16px; border: 1px solid var(--border); 
            margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); 
        }

        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 30px; }
        .stat-box { 
            background: var(--card); backdrop-filter: blur(16px); padding: 24px; 
            border-radius: 16px; border: 1px solid var(--border); text-align: center; 
            transition: var(--smooth); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); 
        }
        .stat-box:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.15); box-shadow: 0 12px 40px rgba(0,0,0,0.3); }
        .stat-num { font-size: 36px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .stat-box div:last-child { color: var(--text-muted); font-size: 13px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; border-radius: 12px; overflow: hidden; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.03); }
        th { background: rgba(15, 23, 42, 0.8); color: var(--text-muted); text-transform: uppercase; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; white-space: nowrap; }
        tr:hover td { background: rgba(255, 255, 255, 0.03); transition: background 0.2s ease; }

        /* Forms */
        input, select { 
            width: 100%; padding: 14px; background: rgba(0,0,0,0.2); 
            border: 1px solid var(--border); color: #fff; border-radius: 10px; 
            margin-bottom: 20px; box-sizing: border-box; font-family: inherit; 
            font-size: 14px; transition: var(--smooth); outline: none; 
        }
        input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); background-color: rgba(0,0,0,0.4); }
        label { font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 8px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }

        .btn { 
            padding: 12px 24px; background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); 
            color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; 
            font-size: 13px; letter-spacing: 0.3px; transition: var(--smooth); display: inline-flex; 
            justify-content: center; align-items: center; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3); 
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(14, 165, 233, 0.5); }
        
        /* TOAST NOTIFICATIONS */
        #toast-container {
            position: fixed; bottom: 30px; right: 30px; z-index: 999999;
            display: flex; flex-direction: column; gap: 12px; pointer-events: none;
        }
        .toast {
            padding: 14px 24px; border-radius: 10px; color: #fff; font-size: 14px; font-weight: 600;
            letter-spacing: 0.3px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            animation: toastSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .toast.success { background: rgba(16, 185, 129, 0.9); border-left: 4px solid #059669; }
        .toast.error { background: rgba(239, 68, 68, 0.9); border-left: 4px solid #b91c1c; }
        .toast.warning { background: rgba(245, 158, 11, 0.9); border-left: 4px solid #d97706; }
        .toast.fade-out { animation: toastFadeOut 0.4s ease forwards; }

        @keyframes toastSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastFadeOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">ADMIN PANEL</div>
        <div class="nav-item active" onclick="showTab('dashboard')">Dashboard</div>
        <div class="nav-item" onclick="showTab('users')">User Management</div>
        <div class="nav-item" onclick="showTab('settings')">Global Settings</div>
        <div class="nav-item" onclick="showTab('logs')">Activity Logs</div>
        <a href="index.html" class="back-btn">Exit to App</a>
    </div>

    <div class="main">
        
        <div id="dashboard" class="section active">
            <h2>System Overview</h2>
            <div class="stat-grid">
                <div class="stat-box"><div class="stat-num" id="statUsers">0</div><div>Total Users</div></div>
                <div class="stat-box"><div class="stat-num" id="statItems">0</div><div>Total Items</div></div>
                <div class="stat-box"><div class="stat-num" id="statLogs">0</div><div>Activity Logs</div></div>
            </div>
            <div class="card">
                <h3>Quick Actions</h3>
                <p>Welcome, Admin. Select a tab from the left to manage the system.</p>
            </div>
        </div>

        <div id="users" class="section">
            <h2>User Management</h2>
            <div class="card">
                <h3>Create New User</h3>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="newUsername" placeholder="Username" aria-label="New Username">
                    <input type="password" id="newPassword" placeholder="Password" aria-label="New Password">
                    <select id="newRole" style="width: 150px;" aria-label="User Role"><option value="user">User</option><option value="admin">Admin</option></select>
                    <button class="btn" onclick="createUser()">Create</button>
                </div>
            </div>
            <div class="card">
                <h3>Existing Users</h3>
                <table id="userTable">
                    <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div id="settings" class="section">
            <h2>Global Settings</h2>
            
            <div class="card">
                <label for="setAppName">Application Name</label>
                <input type="text" id="setAppName">
                
                <label for="setCompName">Company Name (Footer)</label>
                <input type="text" id="setCompName">
                
                <label for="setLowStock">Low Stock Warning Limit</label>
                <input type="number" id="setLowStock">

                <hr style="border: none; border-top: 1px solid var(--border); margin: 25px 0;">

                <h3 style="color: var(--accent); margin-bottom: 15px;">Snipe-IT Integration</h3>
                
                <label for="setSnipeUrl">Snipe-IT API URL</label>
                <input type="text" id="setSnipeUrl" placeholder="http://10.6.12.144:8080">
                
                <label for="setSnipeToken">Snipe-IT API Token (Bearer)</label>
                <input type="password" id="setSnipeToken" placeholder="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...">
                
                <button class="btn" onclick="saveSettings()" style="margin-top: 10px;">Save Configuration</button>
            </div>

            <div class="card">
                <h3>Full System Backup (Smart Chunking)</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">
                    Safely download the entire database and all gigabytes of images. This uses background chunking so it will never crash your server.
                </p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button class="btn" onclick="downloadBackup()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        📦 Download Full System Backup
                    </button>
                </div>
            </div>

            <div class="card">
                <h3>User-Specific Backup & Restore</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">
                    Select a user below to download their specific data. You can also restore a backup file directly into the selected user's account.
                </p>
                
                <label for="backupUserSelect" style="display: none;">Select User for Backup</label>
                <select id="backupUserSelect" style="width: 100%; padding: 14px; margin-bottom: 20px;" aria-label="Select a User">
                    <option value="">-- Select a User --</option>
                </select>

                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <button class="btn" onclick="downloadUserBackup()" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        ⬇️ Download User Backup
                    </button>
                    
                    <label for="restoreTabSelect" style="display: none;">Select Tab to Restore</label>
                    <select id="restoreTabSelect" style="width: 180px; margin-bottom: 0; padding: 12px; font-size: 13px;" aria-label="Select Tab">
                        <option value="all">Restore ALL Tabs</option>
                        <option value="hardware">Hardware Only</option>
                        <option value="software">Cnsumable Only</option>
                        <option value="network">Scrap Only</option>
                    </select>

                    <button class="btn" onclick="document.getElementById('restoreUserZip').click()" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        🔄 Selective Restore
                    </button>
                    <input type="file" id="restoreUserZip" accept=".zip" style="display: none;" onchange="uploadUserRestore(this)">
                </div>
            </div>
        </div> <div id="logs" class="section">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>System Activity Logs</h2>
                <div style="display:flex; gap:10px;">
                    <select id="logUserFilter" onchange="fetchLogs()" style="width:200px; padding:8px; margin:0;" aria-label="Filter Logs by User">
                        <option value="">All Users</option>
                    </select>
                    <button class="btn" onclick="fetchLogs()" style="background:#334155;">Refresh</button>
                </div>
            </div>
            <div class="card">
                <table id="logTable">
                    <thead>
                        <tr>
                            <th width="15%">Time</th>
                            <th width="15%">User</th>
                            <th width="15%">Action</th>
                            <th width="55%">Details</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>

<script>
    const API = "api_admin.php";

    // ==========================================
    // 1. UI & NAVIGATION
    // ==========================================
    function showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        let icon = type === 'success' ? '✅ ' : type === 'error' ? '❌ ' : '⚠️ ';
        toast.innerText = icon + message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }

    function showTab(tab) {
        document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        document.getElementById(tab).classList.add('active');
        
        if (window.event && window.event.target) {
            window.event.target.classList.add('active');
        }
        
        if(tab === 'dashboard') fetchStats();
        if(tab === 'users') fetchUsers();
        if(tab === 'logs') { loadLogUsers(); fetchLogs(); }
        if(tab === 'settings') { fetchSettings(); loadBackupUsers(); }
    }

    // ==========================================
    // 2. DASHBOARD
    // ==========================================
    function fetchStats() {
        fetch(API + "?action=stats").then(r=>r.json()).then(d => {
            if(d.stats) {
                document.getElementById('statUsers').innerText = d.stats.users || 0;
                document.getElementById('statItems').innerText = d.stats.items || 0;
                document.getElementById('statLogs').innerText = d.stats.logs || 0;
            }
        }).catch(e => console.log("Stats error", e));
    }

    // ==========================================
    // 3. USERS
    // ==========================================
    function fetchUsers() {
        fetch("api_users.php").then(r=>r.json()).then(d => {
            let h = "";
            if(d.data) {
                d.data.forEach(u => {
                    h += `<tr>
                        <td>${u.id}</td>
                        <td>${u.username}</td>
                        <td><span style="background:${u.role=='admin'?'#6366f1':'#334155'}; padding:2px 6px; border-radius:4px; font-size:10px;">${u.role.toUpperCase()}</span></td>
                        <td>
                            <button onclick="resetPass(${u.id}, '${u.username}')" style="color:orange; background:none; border:none; cursor:pointer;">Reset Pass</button>
                            <button onclick="deleteUser(${u.id}, '${u.username}')" style="color:red; background:none; border:none; cursor:pointer; margin-left:10px;">Delete</button>
                        </td>
                    </tr>`;
                });
            }
            document.querySelector("#userTable tbody").innerHTML = h;
        }).catch(e => console.log("Users error", e));
    }

    function createUser() {
        const u = document.getElementById('newUsername').value;
        const p = document.getElementById('newPassword').value;
        const r = document.getElementById('newRole').value;
        fetch("api_users.php", {
            method: "POST", body: JSON.stringify({username:u, password:p, role:r})
        }).then(r=>r.json()).then(d => { alert(d.message); fetchUsers(); });
    }

    function deleteUser(id, name) {
        if(confirm("Are you sure you want to PERMANENTLY delete user: " + name + "?")) {
            fetch("api_users.php", { method: "DELETE", body: JSON.stringify({id: id}) })
            .then(r => r.json())
            .then(d => { alert(d.message); fetchUsers(); fetchLogs(); });
        }
    }

    function resetPass(id, name) {
        const newPass = prompt("Enter the new password for user: " + name, "");
        if (newPass === null || newPass.trim() === "") return;
        fetch(API + "?action=reset_pass", {
            method: "POST", headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ user_id: id, new_password: newPass })
        }).then(r => r.json()).then(d => {
            if(d.success) { alert("Password updated successfully!"); fetchLogs(); }
            else alert("Error: " + d.message);
        });
    }

    // ==========================================
    // 4. SETTINGS & BACKUPS
    // ==========================================
    function fetchSettings() {
        fetch(API + "?action=get_settings").then(r => r.json()).then(d => {
            if (d.success) {
                document.getElementById('setAppName').value = d.data.app_name || "";
                document.getElementById('setCompName').value = d.data.company_name || "";
                document.getElementById('setLowStock').value = d.data.low_stock_threshold || 5;
                
                // 🚨 NEW: Fetch Snipe-IT Settings
                document.getElementById('setSnipeUrl').value = d.data.snipeit_url || "";
                document.getElementById('setSnipeToken').value = d.data.snipeit_token || "";
                
                localStorage.setItem('low_stock_threshold', d.data.low_stock_threshold || 5);
            }
        });
    }

    function saveSettings() {
        const threshold = document.getElementById('setLowStock').value;
        const body = {
            app_name: document.getElementById('setAppName').value,
            company_name: document.getElementById('setCompName').value,
            low_stock_threshold: threshold,
            
            // 🚨 NEW: Save Snipe-IT Settings
            snipeit_url: document.getElementById('setSnipeUrl').value,
            snipeit_token: document.getElementById('setSnipeToken').value
        };
        fetch(API + "?action=save_settings", { method: "POST", body: JSON.stringify(body) })
        .then(r => r.json()).then(d => {
            if (d.success) {
                localStorage.setItem('low_stock_threshold', threshold);
                alert("✅ Settings Saved!"); location.reload(); 
            } else alert("❌ Save failed: " + d.message);
        });
    }

    // --- FULL SYSTEM BACKUP (SMART CHUNKING) ---
    async function downloadBackup() {
        if (!confirm("Generate a heavy-duty system backup?\n\nThis will process your database and all images safely in the background. Please do not close the page until it finishes.")) return;

        showToast("Step 1/3: Packing Database...", "warning");

        try {
            let initRes = await fetch("smart_backup.php?action=init").then(r => r.json());
            
            if (!initRes.success) {
                alert("❌ Failed to start backup.");
                return;
            }

            let totalFiles = initRes.total_files;
            let remaining = totalFiles;

            while (remaining > 0) {
                let processed = totalFiles - remaining;
                showToast(`Step 2/3: Zipping Images (${processed} / ${totalFiles})...`, "warning");
                
                let procRes = await fetch("smart_backup.php?action=process").then(r => r.json());
                remaining = procRes.remaining;
            }

            showToast("Step 3/3: Backup Complete! Downloading...", "success");
            window.location.href = "smart_backup.php?action=download";

        } catch (error) {
            alert("❌ A network error occurred during the backup process. Check your server connection.");
            console.error(error);
        }
    }

    // --- USER-SPECIFIC BACKUP ---
    function loadBackupUsers() {
        const selectEl = document.getElementById('backupUserSelect');
        if(!selectEl) return;
        fetch("api_users.php").then(r=>r.json()).then(d => {
            let opts = '<option value="">-- Select a User --</option>';
            if(d.data) d.data.forEach(u => { opts += `<option value="${u.id}">${u.username}</option>`; });
            selectEl.innerHTML = opts;
        });
    }

    // --- SMART USER-SPECIFIC BACKUP (Gigabyte Safe) ---
    async function downloadUserBackup() {
        const uid = document.getElementById('backupUserSelect').value;
        if(!uid) return alert("⚠️ Please select a user first!");
        
        if (!confirm("Generate a heavy-duty backup for this specific user?")) return;

        showToast("Step 1/3: Packing User Database...", "warning");

        try {
            // 1. Init the target user
            let initRes = await fetch("smart_user_backup.php?action=init&user_id=" + uid).then(r => r.json());
            
            if (!initRes.success) {
                alert("❌ Failed to start user backup.");
                return;
            }

            let totalFiles = initRes.total_files;
            let remaining = totalFiles;

            // 2. Zip their specific images 50 at a time
            while (remaining > 0) {
                let processed = totalFiles - remaining;
                showToast(`Step 2/3: Zipping User Images (${processed} / ${totalFiles})...`, "warning");
                
                let procRes = await fetch("smart_user_backup.php?action=process&user_id=" + uid).then(r => r.json());
                remaining = procRes.remaining;
            }

            // 3. Download the final file
            showToast("Step 3/3: User Backup Complete!", "success");
            window.location.href = "smart_user_backup.php?action=download&user_id=" + uid;

        } catch (error) {
            alert("❌ A network error occurred. Check your server connection.");
            console.error(error);
        }
    }

    function uploadUserRestore(input) {
        const uid = document.getElementById('backupUserSelect').value;
        const tab = document.getElementById('restoreTabSelect').value; // 👈 NEW: Grab the tab choice

        if(!uid) { alert("⚠️ Please select a user first."); input.value = ""; return; }
        const file = input.files[0];
        if(!file) return;

        // 👈 NEW: Dynamic Warning Message
        let msg = tab === 'all' ? "WIPE ALL DATA across all tabs" : `WIPE ONLY the ${tab.toUpperCase()} tab`;
        if(!confirm(`⚠️ WARNING: This will ${msg} for the selected user. Proceed?`)) { input.value = ""; return; }
        
        showToast(`Restoring ${tab.toUpperCase()} data...`, "warning");
        const formData = new FormData();
        formData.append("backup_file", file);
        formData.append("user_id", uid);
        formData.append("restore_tab", tab); // 👈 NEW: Send the tab choice to PHP

        fetch("user_restore.php", { method: "POST", body: formData })
        .then(r=>r.json()).then(d=>{
            alert(d.success ? "✅ " + d.message : "❌ Error: " + d.message);
            input.value = "";
        }).catch(err => {
            alert("❌ An error occurred during upload."); input.value = "";
        });
    }

    // ==========================================
    // 5. LOGS
    // ==========================================
    function fetchLogs() {
        const userId = document.getElementById('logUserFilter').value;
        const url = userId ? `${API}?action=get_logs&user_id=${userId}` : `${API}?action=get_logs`;
        
        fetch(url)
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                document.querySelector("#logTable tbody").innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color: var(--danger); font-weight: bold;">⚠️ Error: ${d.message}</td></tr>`;
                return;
            }
            if (!d.data || d.data.length === 0) {
                document.querySelector("#logTable tbody").innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color: var(--text-muted);">No activity logs found.</td></tr>`;
                return;
            }
            let h = "";
            d.data.forEach(l => {
                const color = l.color || '#94a3b8';
                const dateText = l.nice_date || l.created_at || '-';
                h += `<tr>
                    <td style="color:#94a3b8; font-size:11px; white-space:nowrap;">${dateText}</td>
                    <td style="font-weight:600;">${l.username}</td>
                    <td><span style="background:${color}15; color:${color}; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:bold; border:1px solid ${color}40;">${l.action_type}</span></td>
                    <td style="line-height:1.4;">${l.description}</td>
                </tr>`;
            });
            document.querySelector("#logTable tbody").innerHTML = h;
        })
        .catch(err => {
            document.querySelector("#logTable tbody").innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color: var(--danger);">❌ Network Error: Could not connect to database or logs empty.</td></tr>`;
        });
    }

    function loadLogUsers() {
        const filterEl = document.getElementById('logUserFilter');
        if(!filterEl) return;
        fetch("api_users.php").then(r=>r.json()).then(d => {
            let opts = '<option value="">All Users</option>';
            if(d.data) d.data.forEach(u => { opts += `<option value="${u.id}">${u.username}</option>`; });
            filterEl.innerHTML = opts;
        });
    }

    // ==========================================
    // 6. INITIALIZATION
    // ==========================================
    fetchStats();
</script>
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
    }
</script>
</body>
</html>