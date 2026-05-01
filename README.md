# CabEvac: GIS-based Evacuation Center Mapping System

CabEvac is an advanced, web-based Geographic Information System (GIS) application designed to map and manage evacuation centers within Cabanatuan City. It integrates dynamic flood risk zones, precise administrative boundaries, and interactive location tools to provide citizens and administrators with real-time, actionable disaster-response data.

## 🚀 Key Features

### Public Map Interface
- **Interactive Map:** High-performance mapping powered by Leaflet and Mapbox, providing smooth zooming and panning across Cabanatuan City.
- **Dynamic Layers & Legend:** Toggle visibility of various GIS layers including:
  - Flood Risk Zones (Very High, High, Moderate, Low Risk)
  - Barangay Boundaries
  - Active and Inactive Evacuation Centers
- **Responsive UI/UX (Skill ProMax):** 
  - Features a stunning glassmorphism design aesthetic with fluid micro-animations.
  - Custom Desktop Sidebar and swipeable Mobile Bottom Sheet for viewing details.
- **Advanced Image Gallery:** 
  - Integrated auto-slideshow logic for evacuation center images.
  - Hover effects ("VIEW IMAGE" overlays) and dot indicators.
  - Fullscreen lightbox modal with swipe and keyboard navigation support.
- **Location Tools:** Generate Google Maps routing directions and copy GPS coordinates with a single click.

### Administrative Dashboard
- **Secure Access:** Dedicated `/admin` route for system administrators.
- **Center Management:** Full CRUD (Create, Read, Update, Delete) capabilities for evacuation centers.
- **Image Management:** Drag-and-drop image uploading for evacuation centers, keeping original filenames intact, with real-time UI previews.
- **GIS Layer Manager:** Upload and configure GeoJSON data layers directly from the web interface.

---

## 💻 Tech Stack

- **Frontend:** Vanilla JavaScript (ES6+), HTML5, Vanilla CSS (Custom styling, BEM methodology, Glassmorphism elements).
- **Map Engine:** Leaflet.js with Mapbox Tiles.
- **Backend:** PHP 8+ (Procedural and Object-Oriented endpoints).
- **Database:** MySQL (via PDO for secure, parameterized queries).
- **Security:** DOMPurify for frontend XSS mitigation, secure password hashing, CSRF protections.

---

## 🛠️ Prerequisites

To run this project locally, ensure you have the following installed:
1. **XAMPP / WAMP / MAMP:** A local server environment containing Apache and MySQL.
2. **PHP 8.0 or higher:** Ensure the PHP extension `pdo_mysql` is enabled.
3. **Composer (Optional):** For potential PHP dependency management.
4. **Git:** To clone the repository.

---

## ⚙️ Step-by-Step Setup Guide

Follow these steps to get the CabEvac system running on your local machine:

### 1. Clone the Repository
Clone the project into your local server's document root (e.g., `C:\xampp\htdocs\`).
```bash
cd C:\xampp\htdocs\
git clone <repository_url> cabevac
cd cabevac
```

### 2. Set Up the Database
1. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Open your browser and navigate to `http://localhost/phpmyadmin/`.
3. Create a new database named `cabevac_db`.
4. Import the database structure and initial data:
   - Click on the newly created `cabevac_db`.
   - Go to the **Import** tab.
   - Choose the file located at `database/schema.sql` (to create tables) or `database/cabevac_db.sql` (if you want to import the full schema and seed data including GIS polygons).
   - Click **Import** at the bottom of the page.

### 3. Configure Environment Variables
The application connects to the database via environment variables or a config file.
1. Locate the `.env.example` file (if present) or `config.php` file in the `api/` or root directory.
2. Ensure the database credentials match your local MySQL setup (XAMPP defaults):
   ```ini
   DB_HOST=127.0.0.1
   DB_NAME=cabevac_db
   DB_USER=root
   DB_PASS=
   ```

### 4. Create Necessary Directories
Ensure the following directories exist in the project root and are writable (for image uploads and logs):
- `uploads/`
- `logs/`

*(Note: These should already exist due to `.gitkeep` files, but ensure your local server has write permissions to them.)*

### 5. Access the Application
- **Public Map Interface:** Open your browser and go to:
  `http://localhost/cabevac/`
- **Admin Dashboard:** Go to:
  `http://localhost/cabevac/admin/`
  *(Check the database `users` table or seed files for default admin login credentials).*

---

## 📁 Directory Structure
```text
cabevac/
├── admin/            # Admin dashboard UI and pages
├── api/              # PHP backend endpoints (RESTful)
│   ├── admin/        # Secured API endpoints for admin actions
│   └── ...           # Public API endpoints (centers, layers)
├── css/              # Core stylesheets (index.css)
├── database/         # SQL dumps, seeders, and migration scripts
├── images/           # Static asset images and icons
├── js/               # Frontend JavaScript logic
│   ├── uiService.js  # Map controls, sidebars, lightbox, toast notifications
│   ├── mobileSheet.js# Mobile bottom sheet swipe and gallery logic
│   └── ...
├── uploads/          # User-uploaded images for evacuation centers
├── index.php         # Main public application entry point
└── README.md         # Project documentation
```

## 🤝 Contribution Guidelines
When making changes, please adhere to the project's coding standards:
- Always use `camelCase` for JS variables and `snake_case` for database columns.
- Ensure CSS additions follow the existing "Skill ProMax" glassmorphism theme logic.
- Do not introduce external heavy libraries unless explicitly approved. Keep it Vanilla!

---
*Built for the safety and preparedness of Cabanatuan City.*
