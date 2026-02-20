# README - FAQ Admin

## Setup Instructions

### Local Development
1. Copy `.env.example` to `.env`
2. Update `.env` with your database credentials
3. Run: `php -S localhost:8000 -t api/admin`
4. Visit: `http://localhost:8000/login.php`

### Vercel Deployment
1. Connect this repo to Vercel
2. Add these environment variables:
   - DB_HOST
   - DB_USER
   - DB_PASSWORD
   - DB_NAME
   - CLIENT_URL (URL of client project)

3. Deploy!

### Database
Uses shared MySQL database (same as client).

### File Structure
```
api/admin/
├── db.php - Database connection
├── dashboard.php - Main dashboard
├── articles.php - Manage articles
├── sidebar.php - Navigation
└── ... (other admin files)
```
