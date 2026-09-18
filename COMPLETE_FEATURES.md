# AcadeAlert — Complete Academic Productivity Platform

A full-stack, AI-powered academic productivity platform for university students with four independently-owned modules, NotebookLM integration, and advanced study features.

## 🎯 Core Features

### 1. **Common Platform Layer**
- ✅ User authentication (registration, login, logout)
- ✅ Student profile management
- ✅ Module management (subjects/courses)
- ✅ Central dashboard with overview
- ✅ Unified calendar (merged events)
- ✅ Cross-module notifications
- ✅ Global search across all data
- ✅ Dark/light mode toggle
- ✅ Data export & Firebase backup
- ✅ Settings & preferences

### 2. **Learning Materials (Bethmi)**
- ✅ Upload lecture notes (PDF, Word, TXT)
- ✅ Automatic text extraction
- ✅ Document organization by module
- ✅ Full-text search
- ✅ Document renaming & deletion
- ✅ Keyword extraction
- ✅ **AI-powered summaries** (Short/Medium/Detailed)
- ✅ Bullet-point revision notes
- ✅ Summary download & management
- ✅ **NotebookLM Audio Guides** — AI-narrated study materials
- ✅ **Flashcard generation** — Spaced repetition decks
- ✅ **Concept extraction** — Key ideas & learning objectives

### 3. **Study & Engagement (Pasindu)**
- ✅ Focus timer (Pomodoro-style)
- ✅ Start/pause/resume/stop sessions
- ✅ Module & assignment selection
- ✅ Planned vs actual study time tracking
- ✅ Engagement level monitoring (Low/Moderate/Good)
- ✅ Break recommendations
- ✅ Weekly study analytics
- ✅ Daily study goal tracking
- ✅ Study streak counter
- ✅ Session history
- ✅ Productivity statistics
- ✅ **Webcam-based engagement detection** (optional)
- ✅ **Study session reminders**
- ✅ **Personalized study suggestions**

### 4. **Academic Assistant (Kavishka)**
- ✅ RAG chatbot over indexed documents
- ✅ Natural language Q&A
- ✅ Source citations (document, section, page)
- ✅ Conversation history
- ✅ Academic document upload & indexing
- ✅ **Extract academic dates** (deadlines, exams, milestones)
- ✅ Calendar integration
- ✅ **Interactive study guides** — AI-generated from documents
- ✅ **Quiz generation** — Auto-created assessments
- ✅ **Comprehension analysis** — Identify learning gaps

### 5. **Assignment & Deadline Risk (Jithmi)**
- ✅ Add/edit/delete assignments
- ✅ Set deadlines & priorities
- ✅ Estimate workload (hours)
- ✅ Track progress (0-100%)
- ✅ **AI Risk Scoring** (0-100 → Low/Medium/High/Critical)
- ✅ Urgency ranking
- ✅ Risk reasons & explanations
- ✅ Daily study hour recommendations
- ✅ **What-if scenario planner** — Simulate different study hours
- ✅ Assignment completion tracking
- ✅ Deadline notifications
- ✅ Submission history

## 🤖 AI & NotebookLM Features

### Audio Study Guides
- Generate AI-narrated audio from any document
- Adjustable voice & speed
- Transcript included
- Perfect for commute studying

### Interactive Quizzes
- Auto-generate questions from documents
- Multiple question types (MCQ, short answer, true/false)
- Difficulty levels
- Instant feedback

### Flashcard Decks
- Spaced repetition algorithm
- Auto-generated from key concepts
- Customizable card count
- Progress tracking

### Personalized Study Plans
- Analyze available study time
- Match learning style (visual, auditory, reading, kinesthetic)
- Session-by-session breakdown
- Milestone tracking

### Concept Extraction
- Identify key concepts automatically
- Learning objectives
- Difficulty assessment
- Estimated study time

### Comprehension Analysis
- Clarity scoring
- Complexity level detection
- Gap identification
- Improvement recommendations

## 🏗️ Architecture

```
project/
├─ frontend-react/          # React + Material UI web client
│  ├─ src/
│  │  ├─ components/        # Page components (Dashboard, Learning, Study, etc.)
│  │  ├─ context/           # AppContext (auth, theme, notifications)
│  │  ├─ api/               # API client with interceptors
│  │  ├─ firebase/          # Firebase config & real-time listeners
│  │  ├─ theme.js           # Material UI theme (light/dark)
│  │  └─ styles/            # Global CSS with animations
│  └─ package.json
│
├─ backend-laravel/         # Laravel 11 REST API
│  ├─ app/
│  │  ├─ Http/Controllers/Api/  # 11 controllers
│  │  ├─ Models/                # 14 Eloquent models
│  │  └─ Services/              # RiskCalculator, SummaryGenerator, NotebookLMService
│  ├─ routes/api.php            # 40+ endpoints
│  ├─ database/
│  │  ├─ migrations/            # 7 migrations
│  │  └─ seeders/               # Demo data
│  └─ config/                   # 14 config files
│
├─ mobile-app/              # React Native (Expo) client
│  └─ src/                  # Screens, navigation, API client
│
└─ docs/                    # Architecture, API, setup guides
```

## 🚀 Quick Start

### Frontend (React)
```bash
cd frontend-react
npm install
npm run dev
# → http://localhost:5173
```

### Backend (Laravel)
```bash
cd backend-laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
# → http://localhost:8000
```

### Mobile (React Native)
```bash
cd mobile-app
npm install
npm start
# Press 'a' for Android or 'i' for iOS
```

## 📊 Database Schema

**16 Tables + 1 View:**
- users, modules, notifications
- documents, summaries
- study_sessions, engagement_logs
- assignments, risk_assessments
- academic_documents, academic_chunks, academic_dates
- chat_conversations, chat_messages
- personal_access_tokens

## 🔐 Authentication

- **Sanctum Bearer Tokens** — Stateless API authentication
- **Automatic token refresh** — 30-day expiration
- **CORS enabled** — Frontend & mobile origins whitelisted
- **Firebase token registration** — Push notifications

## 🎨 UI/UX Features

- **Material UI 5** — Professional component library
- **Dark/Light mode** — System preference detection
- **Responsive design** — Mobile-first approach
- **Smooth animations** — Spring easing, transitions
- **Glassmorphism** — Modern frosted glass effects
- **Progress indicators** — Linear & circular progress bars
- **Real-time notifications** — Firebase Cloud Messaging
- **Search with autocomplete** — Global search across all modules

## 📱 Responsive Breakpoints

- **Mobile** (xs): < 600px
- **Tablet** (sm): 600px - 960px
- **Desktop** (md): 960px - 1264px
- **Large** (lg): 1264px - 1904px
- **XL** (xl): > 1904px

## 🔌 API Endpoints (40+)

### Auth
- `POST /api/register` — Create account
- `POST /api/login` — Sign in
- `POST /api/logout` — Sign out
- `GET /api/me` — Current user
- `PUT /api/me` — Update profile

### Dashboard
- `GET /api/dashboard` — Overview hub

### Learning Materials
- `GET /api/documents` — List documents
- `POST /api/documents` — Upload document
- `GET /api/documents/{id}` — View document
- `PUT /api/documents/{id}` — Update document
- `DELETE /api/documents/{id}` — Delete document
- `GET /api/summaries` — List summaries
- `POST /api/documents/{id}/summaries` — Generate summary
- `DELETE /api/summaries/{id}` — Delete summary

### Study Sessions
- `GET /api/study/sessions` — List sessions
- `POST /api/study/sessions` — Start session
- `PATCH /api/study/sessions/{id}` — Update session
- `POST /api/study/sessions/{id}/engagement` — Log engagement
- `GET /api/study/analytics` — Weekly analytics

### Assignments
- `GET /api/assignments` — List assignments
- `POST /api/assignments` — Create assignment
- `PUT /api/assignments/{id}` — Update assignment
- `DELETE /api/assignments/{id}` — Delete assignment
- `GET /api/assignments/rank` — Ranked by urgency
- `GET /api/assignments/recommendation` — Top task + study plan
- `POST /api/assignments/whatif` — Scenario planner

### Academic Assistant
- `GET /api/assistant/knowledge` — List documents
- `POST /api/assistant/knowledge` — Add document
- `POST /api/assistant/chat` — Send message
- `GET /api/assistant/dates` — List academic dates
- `POST /api/assistant/dates` — Add date

### AI Features (NotebookLM)
- `GET /api/ai/features` — List available features
- `POST /api/ai/audio-guide` — Generate audio guide
- `POST /api/ai/quiz` — Generate quiz
- `POST /api/ai/flashcards` — Generate flashcards
- `POST /api/ai/study-plan` — Generate study plan
- `POST /api/ai/concepts` — Extract concepts
- `POST /api/ai/analyze` — Analyze comprehension

### Calendar & Notifications
- `GET /api/calendar` — Merged event feed
- `GET /api/notifications` — List notifications
- `PATCH /api/notifications/{id}` — Mark as read
- `POST /api/notifications/read-all` — Mark all read

### Search & Export
- `GET /api/search?q=term` — Global search
- `GET /api/export` — Download JSON backup
- `POST /api/backup/firebase` — Push to Firebase

## 🔧 Configuration

### Environment Variables (.env)
```
APP_NAME=AcadeAlert
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=acadealert
DB_USERNAME=root
DB_PASSWORD=

FIREBASE_CREDENTIALS=storage/app/private/firebase-service-account.json
NOTEBOOKLM_API_KEY=your_api_key_here
```

## 📚 Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — System design & module boundaries
- [`docs/API.md`](docs/API.md) — Complete API reference
- [`docs/DATABASE.md`](docs/DATABASE.md) — Schema & relationships
- [`docs/SETUP-BACKEND.md`](docs/SETUP-BACKEND.md) — Laravel setup
- [`docs/SETUP-WEB.md`](docs/SETUP-WEB.md) — React setup
- [`docs/SETUP-MOBILE.md`](docs/SETUP-MOBILE.md) — Expo setup

## 👥 Team & Modules

| Module | Owner | Responsibility |
|--------|-------|-----------------|
| **Learning Materials** | Bethmi | Documents, summaries, AI guides |
| **Study & Engagement** | Pasindu | Timer, analytics, engagement |
| **Academic Assistant** | Kavishka | Chatbot, dates, quizzes |
| **Assignment Risk** | Jithmi | Risk scoring, planning |
| **Common Platform** | Shared | Auth, dashboard, calendar |

## 🧪 Demo Credentials

```
Email: student@edusmart.lk
Password: password
```

## 📦 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | React 18, Material UI 5, Vite |
| Mobile | React Native, Expo SDK 51 |
| Backend | Laravel 11, PHP 8.2 |
| Database | MySQL 8, Firebase Firestore |
| Auth | Laravel Sanctum |
| AI | NotebookLM API (with offline fallbacks) |
| Notifications | Firebase Cloud Messaging |

## ✅ Verification Checklist

- ✅ Web frontend (8 pages, all interactive)
- ✅ Mobile app (822 modules, offline-first)
- ✅ Backend API (40+ endpoints, complete)
- ✅ Database (16 tables, migrations)
- ✅ Authentication (Sanctum tokens)
- ✅ AI Features (NotebookLM integration)
- ✅ Real-time (Firebase listeners)
- ✅ Dark mode (system preference)
- ✅ Responsive design (mobile-first)
- ✅ Error handling (graceful degradation)

## 🚀 Deployment

### Frontend
```bash
npm run build
# Deploy dist/ to Vercel, Netlify, or AWS S3
```

### Backend
```bash
composer install --no-dev
php artisan migrate --force
php artisan config:cache
# Deploy to Heroku, AWS, or DigitalOcean
```

### Mobile
```bash
eas build --platform all
# Submit to App Store & Google Play
```

## 📄 License

Built as a university group project. No license asserted.

---

**Last Updated:** 2026-01-01  
**Version:** 1.0.0  
**Status:** Production Ready ✅
