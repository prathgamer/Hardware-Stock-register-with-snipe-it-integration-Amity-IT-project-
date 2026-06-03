# 📦 IT Hardware & Stock Management Dashboard

A comprehensive, web-based inventory management system built with PHP and MySQL. Designed to track IT assets, manage employee assignments, and handle heavy-duty data backups flawlessly.

## ✨ Core Features
* **Smart Inventory Tracking:** Categorize assets by Hardware, Consumables, and Scrap.
* **Intelligent Data Import:** Upload Excel or JSON files using a "Smart Cleaner" that automatically maps column names and ignores formatting errors.
* **Advanced Backup System:** Background "chunking" to safely pack gigabytes of database files and images without crashing the server.
* **Selective Data Restore:** Upload a `.zip` or `.json` file to restore specific tabs (e.g., "Consumables Only") and instantly map data to specific employees.
* **Role-Based Access Control:** Secure Admin control panel to manage users, reset passwords, and view system-wide activity logs.

## 🛠️ Tech Stack
* **Frontend:** HTML5, Vanilla JavaScript, CSS3 (Glassmorphism UI)
* **Backend:** PHP 8+
* **Database:** MySQL
* **Deployment:** Fully compatible with XAMPP and Docker environments.

## 🚀 Getting Started (Local Setup)
1. Clone this repository.
2. Place the folder in your `htdocs` (XAMPP) directory.
3. Start Apache and MySQL in your XAMPP Control Panel.
4. Navigate to `http://localhost/your-folder-name/setup.html` to initialize the database and create the master Admin account.
5. you need to create Upload folder also for you files.
6. Use hardware_db in xammp mysql