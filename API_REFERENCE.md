# AcadeAlert API Reference — Complete Endpoint Documentation

**Base URL**: `http://localhost:8000/api`  
**Authentication**: Bearer token (Sanctum)  
**Content-Type**: `application/json`

---

## 🔐 Authentication Endpoints

### Register
```
POST /register
Body: {
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password",
  "password_confirmation": "password",
  "program": "BSc Computer Science"
}
Response: { "user": {...}, "token": "..." }
```

### Login
```
POST /login
Body: {
  "email": "student@edusmart.lk",
  "password": "password"
}
Response: { "user": {...}, "token": "..." }
```

### Logout
```
POST /logout
Headers: Authorization: Bearer {token}
Response: { "message": "Logged out" }
```

### Get Current User
```
GET /me
Headers: Authorization: Bearer {token}
Response: { "id": 1, "name": "...", "email": "...", ... }
```

### Update Profile
```
PUT /me
Headers: Authorization: Bearer {token}
Body: {
  "name": "New Name",
  "program": "New Program",
  "academic_year": "Year 3",
  "dark_mode": true,
  "daily_target_minutes": 180
}
Response: { "id": 1, "name": "...", ... }
```

### Register Firebase Token
```
PUT /me/firebase-token
Headers: Authorization: Bearer {token}
Body: { "token": "firebase_device_token" }
Response: { "message": "Token registered" }
```

---

## 📊 Dashboard Endpoint

### Get Dashboard Overview
```
GET /dashboard
Headers: Authorization: Bearer {token}
Response: {
  "documents_count": 8,
  "active_assignments": 5,
  "risk_score": 62,
  "upcoming_dates": 4,
  "streak_days": 7,
  "sessions_today": 2,
  "studied_today_minutes": 95,
  "daily_target_minutes": 180,
  "top_assignments": [...],
  "upcoming_events": [...]
}
```

---

## 📚 Learning Materials Endpoints

### List Documents
```
GET /documents?q=search&module_id=1&type=PDF
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "title": "Lecture Notes",
    "topic": "Inheritance",
    "type": "PDF",
    "pages": 24,
    "size_bytes": 2400000,
    "module": { "id": 1, "name": "OOP" },
    "extracted_text": "..."
  }
]
```

### Upload Document
```
POST /documents
Headers: Authorization: Bearer {token}
Body: FormData {
  "title": "Lecture 5",
  "topic": "Polymorphism",
  "module_id": 1,
  "type": "PDF",
  "file": <binary>
}
Response: { "id": 1, "title": "...", ... }
```

### Get Document
```
GET /documents/{id}
Headers: Authorization: Bearer {token}
Response: { "id": 1, "title": "...", "summaries": [...] }
```

### Update Document
```
PUT /documents/{id}
Headers: Authorization: Bearer {token}
Body: {
  "title": "New Title",
  "topic": "New Topic",
  "extracted_text": "..."
}
Response: { "id": 1, "title": "...", ... }
```

### Delete Document
```
DELETE /documents/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Document deleted" }
```

### Extract Keywords
```
GET /documents/{id}/keywords
Headers: Authorization: Bearer {token}
Response: { "keywords": ["inheritance", "polymorphism", "override"] }
```

### List Summaries
```
GET /summaries
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "title": "Summary Title",
    "length_type": "Medium",
    "body": "...",
    "keywords": ["key1", "key2"],
    "text": "...",
    "length": "Medium"
  }
]
```

### Generate Summary
```
POST /documents/{id}/summaries
Headers: Authorization: Bearer {token}
Body: {
  "length_type": "Medium",
  "title": "Custom Title"
}
Response: { "id": 1, "title": "...", "body": "...", ... }
```

### Get Summary
```
GET /summaries/{id}
Headers: Authorization: Bearer {token}
Response: { "id": 1, "title": "...", "body": "...", ... }
```

### Delete Summary
```
DELETE /summaries/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Summary deleted" }
```

---

## ⏱️ Study Session Endpoints

### List Sessions
```
GET /study/sessions?from=2026-01-01&to=2026-01-31
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "module_id": 1,
    "assignment_id": 1,
    "document_id": 1,
    "activity": "Revision",
    "planned_minutes": 25,
    "actual_minutes": 28,
    "status": "completed",
    "started_at": "2026-01-15T10:00:00Z",
    "ended_at": "2026-01-15T10:28:00Z"
  }
]
```

### Get Current Session
```
GET /study/current
Headers: Authorization: Bearer {token}
Response: { "id": 1, "status": "active", ... } or null
```

### Start Session
```
POST /study/sessions
Headers: Authorization: Bearer {token}
Body: {
  "module_id": 1,
  "assignment_id": 1,
  "document_id": 1,
  "activity": "Revision",
  "planned_minutes": 25
}
Response: { "id": 1, "status": "active", ... }
```

### Update Session
```
PATCH /study/sessions/{id}
Headers: Authorization: Bearer {token}
Body: {
  "status": "completed",
  "actual_minutes": 28
}
Response: { "id": 1, "status": "completed", ... }
```

### Log Engagement
```
POST /study/sessions/{id}/engagement
Headers: Authorization: Bearer {token}
Body: {
  "level": "Good",
  "percent": 85,
  "source": "manual"
}
Response: { "id": 1, "level": "Good", "percent": 85, ... }
```

### Get Analytics
```
GET /study/analytics
Headers: Authorization: Bearer {token}
Response: {
  "weekly": [
    { "label": "Mon", "minutes": 120 },
    ...
  ],
  "planned_minutes": 600,
  "actual_minutes": 715,
  "completion_rate": 119,
  "sessions_count": 14,
  "average_engagement": 72,
  "streak_days": 7,
  "daily_target": 120
}
```

---

## 📋 Assignment Endpoints

### List Assignments
```
GET /assignments?status=upcoming
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "title": "Database Project",
    "module": { "id": 1, "name": "Database Systems" },
    "deadline": "2026-09-25",
    "priority": "High",
    "est_hours": 20,
    "done_hours": 5,
    "progress": 25,
    "completed": false,
    "risk": {
      "score": 90,
      "level": "Critical",
      "hours_per_day": 5.0,
      "reasons": ["Only 3 day(s) left", "Progress is low (25%)"],
      "days": 3
    }
  }
]
```

### Create Assignment
```
POST /assignments
Headers: Authorization: Bearer {token}
Body: {
  "title": "New Assignment",
  "module_id": 1,
  "deadline": "2026-10-01",
  "priority": "High",
  "est_hours": 15,
  "done_hours": 0,
  "progress": 0
}
Response: { "id": 1, "title": "...", "risk": {...} }
```

### Get Assignment
```
GET /assignments/{id}
Headers: Authorization: Bearer {token}
Response: { "id": 1, "title": "...", "risk": {...}, ... }
```

### Update Assignment
```
PUT /assignments/{id}
Headers: Authorization: Bearer {token}
Body: {
  "progress": 50,
  "done_hours": 7.5
}
Response: { "id": 1, "progress": 50, "risk": {...} }
```

### Delete Assignment
```
DELETE /assignments/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Assignment deleted" }
```

### Rank Assignments
```
GET /assignments/rank
Headers: Authorization: Bearer {token}
Response: [
  { "rank": 1, "assignment": {...}, "risk": {...} },
  ...
]
```

### Get Recommendation
```
GET /assignments/recommendation
Headers: Authorization: Bearer {token}
Response: {
  "assignment": {...},
  "risk": {...},
  "remaining_hours": 15,
  "days_left": 3,
  "suggested_per_day": 5.0,
  "suggested_minutes": 300,
  "message": "Focus on ... — critical risk. Aim for ~5.0h/day..."
}
```

### What-If Scenario
```
POST /assignments/whatif
Headers: Authorization: Bearer {token}
Body: {
  "assignment_id": 1,
  "deadline": "2026-10-05",
  "est_hours": 20,
  "done_hours": 5,
  "progress": 25
}
Response: {
  "simulated": {...},
  "risk": {...},
  "note": "Scenario only — nothing was saved."
}
```

---

## 🤖 Academic Assistant Endpoints

### List Knowledge Base
```
GET /assistant/knowledge
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "category": "Handbook",
    "title": "University Handbook",
    "pages": 48,
    "is_indexed": true,
    "chunks_count": 12
  }
]
```

### Add Document to Knowledge Base
```
POST /assistant/knowledge
Headers: Authorization: Bearer {token}
Body: {
  "category": "Handbook",
  "title": "Document Title",
  "pages": 20,
  "chunks": [
    {
      "section": "Chapter 1",
      "page": 1,
      "content": "...",
      "keywords": ["key1", "key2"]
    }
  ]
}
Response: { "id": 1, "title": "...", "chunks_count": 1 }
```

### Delete Knowledge Document
```
DELETE /assistant/knowledge/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Document removed from knowledge base" }
```

### List Conversations
```
GET /assistant/conversations
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "title": "When do exams start?",
    "messages_count": 2,
    "created_at": "2026-01-15T10:00:00Z"
  }
]
```

### Get Conversation Messages
```
GET /assistant/conversations/{id}/messages
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "role": "user",
    "content": "When do exams start?",
    "created_at": "2026-01-15T10:00:00Z"
  },
  {
    "id": 2,
    "role": "bot",
    "content": "Based on the handbook...",
    "source_chunk_id": 5,
    "created_at": "2026-01-15T10:00:05Z"
  }
]
```

### Send Chat Message
```
POST /assistant/chat
Headers: Authorization: Bearer {token}
Body: {
  "message": "When do exams start?",
  "conversation_id": 1
}
Response: {
  "conversation_id": 1,
  "reply": "Based on the handbook...",
  "source": {
    "document": "University Handbook",
    "section": "Academic Calendar",
    "page": 6,
    "score": 0.95
  },
  "message": {...}
}
```

### List Academic Dates
```
GET /assistant/dates
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "title": "Semester 1 Exams",
    "event_date": "2026-10-15",
    "type": "Exam",
    "reminder": "3 days before"
  }
]
```

### Add Academic Date
```
POST /assistant/dates
Headers: Authorization: Bearer {token}
Body: {
  "title": "Project Deadline",
  "event_date": "2026-09-30",
  "type": "Deadline",
  "reminder": "1 day before"
}
Response: { "id": 1, "title": "...", ... }
```

### Update Academic Date
```
PUT /assistant/dates/{id}
Headers: Authorization: Bearer {token}
Body: {
  "title": "Updated Title",
  "event_date": "2026-10-01"
}
Response: { "id": 1, "title": "...", ... }
```

### Delete Academic Date
```
DELETE /assistant/dates/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Date removed" }
```

---

## 📅 Calendar Endpoint

### Get Merged Calendar
```
GET /calendar?from=2026-01-01&to=2026-12-31
Headers: Authorization: Bearer {token}
Response: {
  "from": "2026-01-01",
  "to": "2026-12-31",
  "events": [
    {
      "id": "date-1",
      "title": "Semester 1 Exams",
      "date": "2026-10-15",
      "type": "Exam",
      "source": "kavishka",
      "meta": { "reminder": "3 days before" }
    },
    {
      "id": "assignment-1",
      "title": "Database Project (due)",
      "date": "2026-09-25",
      "type": "Assignment",
      "source": "jithmi",
      "meta": { "risk_level": "Critical", "completed": false }
    }
  ]
}
```

---

## 🔔 Notification Endpoints

### List Notifications
```
GET /notifications?unread=1
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "module_source": "jithmi",
    "title": "Critical risk: Database Project",
    "message": "Due in 3 days and only 25% complete.",
    "is_read": false,
    "created_at": "2026-01-15T10:00:00Z"
  }
]
```

### Create Notification
```
POST /notifications
Headers: Authorization: Bearer {token}
Body: {
  "module_source": "jithmi",
  "title": "Notification Title",
  "message": "Notification message"
}
Response: { "id": 1, "title": "...", ... }
```

### Mark as Read
```
PATCH /notifications/{id}
Headers: Authorization: Bearer {token}
Response: { "id": 1, "is_read": true, ... }
```

### Mark All as Read
```
POST /notifications/read-all
Headers: Authorization: Bearer {token}
Response: { "message": "All notifications marked as read" }
```

### Delete Notification
```
DELETE /notifications/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Notification deleted" }
```

---

## 🧠 AI Features Endpoints (NotebookLM)

### List AI Features
```
GET /ai/features
Headers: Authorization: Bearer {token}
Response: {
  "features": [
    {
      "id": "audio_guide",
      "name": "Audio Study Guide",
      "description": "Generate AI-narrated study guides",
      "icon": "volume_up",
      "color": "#3b82f6"
    },
    ...
  ]
}
```

### Generate Audio Guide
```
POST /ai/audio-guide
Headers: Authorization: Bearer {token}
Body: {
  "document_id": 1,
  "title": "Study Guide"
}
Response: {
  "success": true,
  "audio_url": "https://...",
  "duration": 15,
  "transcript": "..."
}
```

### Generate Quiz
```
POST /ai/quiz
Headers: Authorization: Bearer {token}
Body: {
  "document_id": 1,
  "question_count": 10
}
Response: {
  "success": true,
  "quiz_id": "quiz_123",
  "questions": [
    {
      "id": 1,
      "question": "What is...",
      "type": "multiple_choice",
      "options": ["A", "B", "C", "D"]
    }
  ],
  "estimated_time": 20
}
```

### Generate Flashcards
```
POST /ai/flashcards
Headers: Authorization: Bearer {token}
Body: {
  "document_id": 1,
  "card_count": 20
}
Response: {
  "success": true,
  "deck_id": "deck_123",
  "cards": [
    {
      "id": 1,
      "front": "Question",
      "back": "Answer",
      "level": 1
    }
  ],
  "total_cards": 20
}
```

### Generate Study Plan
```
POST /ai/study-plan
Headers: Authorization: Bearer {token}
Body: {
  "document_id": 1,
  "available_hours": 5,
  "learning_style": "mixed"
}
Response: {
  "success": true,
  "plan_id": "plan_123",
  "sessions": [
    {
      "session_number": 1,
      "duration": 1,
      "focus": "Overview",
      "activities": "Read"
    }
  ],
  "milestones": ["Concepts understood", "Practice completed"],
  "total_hours": 5
}
```

### Extract Concepts
```
POST /ai/concepts
Headers: Authorization: Bearer {token}
Body: { "document_id": 1 }
Response: {
  "success": true,
  "key_concepts": ["Inheritance", "Polymorphism", "Encapsulation"],
  "learning_objectives": ["Understand OOP", "Apply concepts"],
  "difficulty_level": "medium",
  "estimated_study_time": 5
}
```

### Analyze Comprehension
```
POST /ai/analyze
Headers: Authorization: Bearer {token}
Body: { "document_id": 1 }
Response: {
  "success": true,
  "clarity_score": 85,
  "complexity_level": "medium",
  "gaps": ["Missing examples", "Unclear definitions"],
  "recommendations": ["Add more examples", "Simplify language"]
}
```

---

## 🔍 Search Endpoint

### Global Search
```
GET /search?q=inheritance&limit=8
Headers: Authorization: Bearer {token}
Response: {
  "query": "inheritance",
  "total": 3,
  "results": [
    {
      "type": "document",
      "id": 1,
      "label": "OOP Lecture 05",
      "sub": "Programming (OOP) · PDF",
      "url": "/learning?doc=1"
    }
  ],
  "buckets": {
    "documents": [...],
    "assignments": [...],
    "dates": [...],
    "modules": [...]
  }
}
```

---

## 💾 Data Management Endpoints

### Export Data
```
GET /export
Headers: Authorization: Bearer {token}
Response: (JSON file download)
{
  "exportedAt": "2026-01-15T10:00:00Z",
  "user": {...},
  "modules": [...],
  "documents": [...],
  "assignments": [...],
  "studySessions": [...]
}
```

### Backup to Firebase
```
POST /backup/firebase
Headers: Authorization: Bearer {token}
Response: {
  "message": "Backup pushed to Firebase successfully.",
  "success": true,
  "backedUpAt": "2026-01-15T10:00:00Z"
}
```

---

## 📦 Module Endpoints

### List Modules
```
GET /modules
Headers: Authorization: Bearer {token}
Response: [
  {
    "id": 1,
    "code": "SE201",
    "name": "Software Engineering",
    "color": "#3b82f6",
    "icon": "diagram-3",
    "documents_count": 5,
    "assignments_count": 3
  }
]
```

### Create Module
```
POST /modules
Headers: Authorization: Bearer {token}
Body: {
  "code": "CS301",
  "name": "Data Structures",
  "color": "#8b5cf6",
  "icon": "layers"
}
Response: { "id": 1, "code": "...", ... }
```

### Update Module
```
PUT /modules/{id}
Headers: Authorization: Bearer {token}
Body: {
  "name": "Updated Name",
  "color": "#14b8a6"
}
Response: { "id": 1, "name": "...", ... }
```

### Delete Module
```
DELETE /modules/{id}
Headers: Authorization: Bearer {token}
Response: { "message": "Module deleted" }
```

---

## ✅ Health Check

### API Health
```
GET /health
Response: {
  "status": "ok",
  "service": "edu-smart-api",
  "time": "2026-01-15T10:00:00Z"
}
```

---

## 📊 Total Endpoints: 47

- Authentication: 6
- Dashboard: 1
- Learning Materials: 11
- Study Sessions: 6
- Assignments: 8
- Academic Assistant: 10
- Calendar: 1
- Notifications: 5
- AI Features: 6
- Search: 1
- Data Management: 2
- Modules: 4
- Health: 1

---

**Last Updated**: 2026-01-01  
**API Version**: 1.0.0  
**Status**: Production Ready ✅
