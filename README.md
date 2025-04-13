# Live School - World-Class Free Education Platform

A modern web application for delivering free educational content, featuring course management, student progress tracking, and interactive quizzes.

## Features

- User Authentication (Student/Teacher roles)
- Course Management
- PDF and Video Lessons
- Interactive Quizzes
- Progress Tracking
- Modern, Responsive UI
- Student Dashboard
- Teacher Dashboard

## Tech Stack

- Backend: PHP
- Frontend: HTML, Tailwind CSS, JavaScript
- Database: MySQL
- Environment: XAMPP/Laragon (localhost)

## Prerequisites

1. XAMPP (or Laragon) with:
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Apache web server
2. Web browser (Chrome, Firefox, Safari, or Edge)

## Installation

1. Clone or download this repository to your web server's root directory:
   ```bash
   # For XAMPP (Windows)
   C:\xampp\htdocs\live-school

   # For XAMPP (Linux/Mac)
   /opt/lampp/htdocs/live-school

   # For Laragon
   C:\laragon\www\live-school
   ```

2. Create a new MySQL database:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named 'live_school'
   - Import the database schema from `database/schema.sql`

3. Configure the application:
   - Open `config/config.php`
   - Update the database connection settings if needed
   - Update the BASE_URL constant to match your setup

4. Set up the file permissions (Linux/Mac):
   ```bash
   chmod 755 -R /path/to/live-school
   chmod 777 -R /path/to/live-school/uploads
   ```

5. Access the application:
   ```
   http://localhost/live-school
   ```

## Directory Structure

```
live-school/
├── auth/               # Authentication pages
├── config/             # Configuration files
├── courses/            # Course management
├── dashboard/          # User dashboards
├── database/          # Database schema
├── includes/          # Shared components
├── quizzes/           # Quiz functionality
├── uploads/           # User uploads
├── .htaccess         # Apache configuration
└── README.md         # This file
```

## Usage

1. Register as either a student or teacher
2. For Teachers:
   - Create courses with lessons
   - Upload course materials (PDF/video links)
   - Create quizzes
   - Monitor student progress
3. For Students:
   - Browse available courses
   - Enroll in courses
   - Access course materials
   - Take quizzes
   - Track progress

## Security Features

- Password hashing
- SQL injection prevention
- XSS protection
- CSRF protection
- Secure file upload handling
- Role-based access control

## Maintenance

- Regularly backup the database
- Monitor the uploads directory size
- Keep PHP and MySQL updated
- Check error logs regularly

## Support

For issues or questions, please:
1. Check the existing documentation
2. Review your configuration
3. Contact the system administrator

## License

This project is licensed under the MIT License - see the LICENSE file for details.
