# AcadeAlert — Complete Setup & Deployment Guide

## Prerequisites

- **Node.js** 18+ (for frontend & mobile)
- **PHP** 8.2+ (for backend)
- **Composer** (PHP dependency manager)
- **MySQL** 8.0+ (database)
- **Git** (version control)

## 1. Backend Setup (Laravel)

### Step 1: Install Dependencies
```bash
cd backend-laravel
composer install
```

### Step 2: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_NAME=AcadeAlert
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=acadealert
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_EXPIRATION=43200

FIREBASE_CREDENTIALS=storage/app/private/firebase-service-account.json
NOTEBOOKLM_API_KEY=your_notebooklm_api_key_here
```

### Step 3: Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE acadealert CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate --seed

# Or import schema directly
mysql -u root -p acadealert < database/sql/schema.sql
```

### Step 4: Start Server
```bash
php artisan serve
# → http://localhost:8000
```

### Step 5: Verify API
```bash
curl http://localhost:8000/api/health
# Response: {"status":"ok","service":"edu-smart-api","time":"..."}
```

## 2. Frontend Setup (React)

### Step 1: Install Dependencies
```bash
cd frontend-react
npm install
```

### Step 2: Environment Configuration
Create `.env.local`:
```env
VITE_API_URL=http://localhost:8000/api
VITE_FIREBASE_API_KEY=your_firebase_key
VITE_FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your_project_id
VITE_FIREBASE_STORAGE_BUCKET=your_project.appspot.com
VITE_FIREBASE_MESSAGING_SENDER_ID=your_sender_id
VITE_FIREBASE_APP_ID=your_app_id
```

### Step 3: Start Development Server
```bash
npm run dev
# → http://localhost:5173
```

### Step 4: Build for Production
```bash
npm run build
# Output: dist/
```

## 3. Mobile Setup (React Native)

### Step 1: Install Dependencies
```bash
cd mobile-app
npm install
```

### Step 2: Environment Configuration
Create `app.json` updates:
```json
{
  "expo": {
    "extra": {
      "apiUrl": "http://localhost:8000/api"
    }
  }
}
```

### Step 3: Start Development
```bash
npm start
# Press 'a' for Android, 'i' for iOS, or scan QR with Expo Go
```

### Step 4: Build for Production
```bash
# Android
eas build --platform android

# iOS
eas build --platform ios

# Both
eas build --platform all
```

## 4. Firebase Setup

### Step 1: Create Firebase Project
1. Go to [Firebase Console](https://console.firebase.google.com)
2. Create new project
3. Enable Firestore Database
4. Enable Cloud Messaging

### Step 2: Download Service Account
1. Project Settings → Service Accounts
2. Generate new private key
3. Save as `backend-laravel/storage/app/private/firebase-service-account.json`

### Step 3: Configure Web App
1. Project Settings → Your apps
2. Copy config to frontend `.env.local`

## 5. NotebookLM Integration

### Step 1: Get API Key
1. Sign up at [NotebookLM](https://notebooklm.google.com)
2. Generate API key from settings
3. Add to `.env`:
```env
NOTEBOOKLM_API_KEY=your_api_key_here
```

### Step 2: Test Integration
```bash
# Backend will automatically fall back to offline mode if API key is missing
# All AI features work with or without NotebookLM
```

## 6. Database Seeding

### Seed Demo Data
```bash
php artisan migrate --seed
```

### Manual Seeding
```bash
php artisan db:seed --class=DatabaseSeeder
```

### Clear & Reseed
```bash
php artisan migrate:refresh --seed
```

## 7. Testing

### Backend Tests
```bash
cd backend-laravel
php artisan test
```

### Frontend Tests
```bash
cd frontend-react
npm run test
```

### Mobile Tests
```bash
cd mobile-app
npm run test
```

## 8. Deployment

### Deploy Backend (Heroku)
```bash
# Install Heroku CLI
heroku login
heroku create acadealert-api
git push heroku main

# Run migrations
heroku run php artisan migrate --app=acadealert-api
```

### Deploy Frontend (Vercel)
```bash
# Install Vercel CLI
npm i -g vercel
vercel

# Set environment variables in Vercel dashboard
```

### Deploy Mobile (EAS)
```bash
eas build --platform all
eas submit --platform all
```

## 9. Troubleshooting

### Backend Issues

**CORS Error**
```
Solution: Check FRONTEND_URL in .env matches your frontend origin
```

**Database Connection Failed**
```
Solution: Verify MySQL is running and credentials in .env are correct
```

**Sanctum Token Invalid**
```
Solution: Ensure APP_KEY is set (php artisan key:generate)
```

### Frontend Issues

**API Not Responding**
```
Solution: Check VITE_API_URL in .env.local points to running backend
```

**Firebase Not Initializing**
```
Solution: Verify all Firebase config keys in .env.local
```

**Dark Mode Not Working**
```
Solution: Clear localStorage and refresh browser
```

### Mobile Issues

**Expo Connection Failed**
```
Solution: Ensure backend is running and API_URL is correct
```

**Offline Mode Not Activating**
```
Solution: Check network connectivity and bundled demo data
```

## 10. Performance Optimization

### Backend
```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### Frontend
```bash
# Build with optimizations
npm run build

# Analyze bundle size
npm run build -- --analyze
```

### Database
```bash
# Add indexes
php artisan tinker
>>> DB::statement('ALTER TABLE documents ADD FULLTEXT INDEX ft_search (title, topic, extracted_text)');
```

## 11. Monitoring & Logging

### Backend Logs
```bash
tail -f storage/logs/laravel.log
```

### Frontend Console
```
Open DevTools → Console tab
```

### Firebase Logs
```
Firebase Console → Logs
```

## 12. Backup & Recovery

### Database Backup
```bash
mysqldump -u root -p acadealert > backup.sql
```

### Database Restore
```bash
mysql -u root -p acadealert < backup.sql
```

### Firebase Backup
```bash
# Use Firebase export feature in console
```

## 13. Security Checklist

- ✅ Change default credentials
- ✅ Enable HTTPS in production
- ✅ Set strong database password
- ✅ Rotate API keys regularly
- ✅ Enable Firebase security rules
- ✅ Use environment variables for secrets
- ✅ Enable CORS only for trusted origins
- ✅ Set up rate limiting
- ✅ Enable database backups
- ✅ Monitor error logs

## 14. Maintenance

### Weekly
- Check error logs
- Monitor database size
- Verify backups

### Monthly
- Update dependencies
- Review security logs
- Optimize database

### Quarterly
- Full security audit
- Performance review
- Capacity planning

## 15. Support & Documentation

- **API Docs**: `docs/API.md`
- **Architecture**: `docs/ARCHITECTURE.md`
- **Database**: `docs/DATABASE.md`
- **Issues**: GitHub Issues
- **Discussions**: GitHub Discussions

---

**Last Updated:** 2026-01-01  
**Version:** 1.0.0
