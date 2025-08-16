# Attendance System for TaaBia Platform

## Overview

The attendance system has been successfully integrated into the TaaBia learning platform, providing comprehensive attendance tracking for both students and instructors. This system allows instructors to create attendance sessions and track student participation, while students can view their attendance records and statistics.

## Features

### For Students
- **Attendance Dashboard**: View overall attendance statistics and course-specific attendance rates
- **Course Attendance Details**: Detailed view of attendance for each enrolled course
- **Monthly Breakdown**: Track attendance trends over time
- **Attendance Requirements**: See if courses have attendance requirements and whether you're meeting them
- **Recent Records**: View recent attendance sessions and their status

### For Instructors
- **Attendance Management**: Create and manage attendance sessions for courses
- **Session Creation**: Set up attendance sessions with dates, times, and session types
- **Student Tracking**: Monitor student attendance across all courses
- **Statistics Overview**: View attendance statistics and trends
- **Course-specific Management**: Manage attendance settings per course

## Database Structure

The attendance system uses the following database tables:

### Core Tables
- `attendance_sessions`: Stores attendance session information
- `student_attendance`: Records individual student attendance for each session
- `course_attendance_settings`: Configurable attendance settings per course
- `attendance_reports`: Generated attendance reports and statistics

### Key Features
- **Session Types**: lesson, quiz, assignment, meeting, other
- **Attendance Status**: present, absent, late, excused
- **Flexible Settings**: Configurable attendance requirements per course
- **Automatic Tracking**: Students are automatically marked as absent when sessions are created

## Installation

### 1. Database Setup
Run the attendance system setup script:

```bash
php setup_attendance_system.php
```

This will create all necessary database tables and indexes.

### 2. Navigation Updates
The attendance system has been integrated into the existing navigation:

**Student Navigation:**
- Added "Attendance" link in the student sidebar
- Accessible from: `student/attendance.php`

**Instructor Navigation:**
- Added "Gestion de la présence" link in the instructor sidebar
- Accessible from: `instructor/attendance_management.php`

### 3. Language Support
The system supports both English and French with comprehensive translations for all attendance-related terms.

## Usage Guide

### For Students

1. **Access Attendance Dashboard**
   - Log in to your student account
   - Click on "Attendance" in the sidebar
   - View your overall attendance statistics

2. **View Course-specific Attendance**
   - From the attendance dashboard, click "View Details" on any course
   - See detailed attendance records for that specific course
   - View monthly breakdowns and trends

3. **Monitor Requirements**
   - Check if your courses have attendance requirements
   - See whether you're meeting minimum attendance percentages
   - Track your progress over time

### For Instructors

1. **Access Attendance Management**
   - Log in to your instructor account
   - Click on "Gestion de la présence" in the sidebar
   - View overview of all your courses and their attendance data

2. **Create Attendance Sessions**
   - Click "Nouvelle session" to create a new attendance session
   - Select the course, set date/time, and session type
   - Students will be automatically marked as absent initially

3. **Manage Attendance Records**
   - View detailed attendance records for each session
   - Update student attendance status as needed
   - Generate attendance reports

## File Structure

```
├── database/
│   └── attendance_system.sql          # Database schema
├── student/
│   ├── attendance.php                  # Student attendance dashboard
│   └── course_attendance.php          # Course-specific attendance view
├── instructor/
│   ├── attendance_management.php       # Instructor attendance management
│   ├── create_attendance_session.php   # Create new attendance sessions
│   └── get_lessons.php                # AJAX endpoint for lessons
├── lang/
│   ├── en.php                         # English translations
│   └── fr.php                         # French translations
├── setup_attendance_system.php        # Database setup script
└── ATTENDANCE_SYSTEM_README.md        # This file
```

## Key Features

### Attendance Status Types
- **Present**: Student attended the session
- **Absent**: Student did not attend
- **Late**: Student attended but was late
- **Excused**: Student was excused from attendance

### Session Types
- **Lesson**: Regular course lesson
- **Quiz**: Assessment or quiz session
- **Assignment**: Assignment submission or review
- **Meeting**: Group meeting or discussion
- **Other**: Any other type of session

### Configuration Options
- **Attendance Required**: Whether attendance is mandatory for a course
- **Minimum Attendance Percentage**: Required attendance rate (default: 80%)
- **Late Threshold**: Minutes after which a student is considered late
- **Auto-mark Absent**: Automatically mark students absent after a certain time

## Technical Implementation

### Database Relationships
- `attendance_sessions` → `courses` (many-to-one)
- `student_attendance` → `attendance_sessions` (many-to-one)
- `student_attendance` → `users` (many-to-one)
- `course_attendance_settings` → `courses` (one-to-one)

### Security Features
- Role-based access control (students can only view their own attendance)
- Instructor verification for course ownership
- SQL injection prevention through prepared statements
- XSS protection through proper output escaping

### Performance Optimizations
- Database indexes on frequently queried columns
- Efficient queries with proper JOINs
- Pagination for large datasets
- Caching of attendance statistics

## Future Enhancements

### Planned Features
1. **QR Code Attendance**: Generate QR codes for quick attendance marking
2. **Email Notifications**: Notify students of upcoming sessions
3. **Attendance Reports**: Generate PDF reports for attendance
4. **Mobile App Integration**: Attendance tracking via mobile app
5. **Geolocation Tracking**: Verify attendance based on location
6. **Integration with Calendar**: Sync attendance sessions with calendar systems

### API Endpoints
- RESTful API for attendance management
- Webhook support for external integrations
- Real-time attendance updates

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify database credentials in `includes/db.php`
   - Ensure MySQL service is running

2. **Permission Issues**
   - Check file permissions for upload directories
   - Verify user roles are properly set

3. **Language Issues**
   - Ensure language files are properly loaded
   - Check for missing translation keys

### Support
For technical support or feature requests, please contact the development team.

## Version History

- **v1.0.0**: Initial release with basic attendance tracking
- Basic session creation and management
- Student attendance dashboard
- Instructor attendance management
- Multi-language support (English/French)

---

**Note**: This attendance system is designed to be flexible and scalable. It can be easily extended with additional features and integrations as needed. 