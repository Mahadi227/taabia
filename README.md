# TaaBia Skills & Market

**An Integrated Web Platform for Skill-Based Learning and Product Sales**

## 📋 Project Overview

TaaBia is a comprehensive web-based platform that seamlessly combines online learning and e-commerce to support local instructors, digital product vendors, and learners in a single system. The platform addresses the digital gap faced by micro-entrepreneurs and educators in developing regions by providing tools to teach, monetize skills, and manage learners efficiently.

## 🎯 Key Features

### Multi-Role System
- **Admin**: Complete platform management, user oversight, analytics
- **Instructor**: Course creation, student management, earnings tracking
- **Student**: Course enrollment, progress tracking, product purchases
- **Vendor**: Product management, sales analytics, order fulfillment

### Learning Management
- ✅ Course creation and publishing
- ✅ Multi-format content (video, PDF, text, quizzes)
- ✅ Progress tracking and certificates
- ✅ Assignment submission and grading
- ✅ Student-instructor messaging

### E-commerce Integration
- ✅ Product catalog management
- ✅ Shopping cart and checkout
- ✅ Multiple payment gateways (Flutterwave, Stripe, Paystack)
- ✅ Order management and tracking
- ✅ Vendor earnings and payouts

### Advanced Features
- ✅ Event management and registration
- ✅ Real-time analytics and reporting
- ✅ Mobile-responsive design
- ✅ Secure authentication system
- ✅ File upload and management

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 7.4+
- **Database**: MySQL 8.0+
- **Payment**: Flutterwave, Stripe, Paystack integration
- **Design**: Responsive design with modern UI/UX
- **Security**: PDO prepared statements, password hashing, session management

## 📁 Project Structure

```
taabia/
├── admin/                 # Admin dashboard and management
├── auth/                  # Authentication system
├── database/              # Database schema and migrations
├── includes/              # Core functions and database connection
├── instructor/            # Instructor dashboard and course management
├── Payment/               # Payment gateway implementations
├── public/                # Public-facing website
│   └── main_site/        # Landing page and public pages
├── student/               # Student dashboard and course access
├── vendor/                # Vendor dashboard and product management
├── uploads/               # File uploads (images, documents)
└── assets/                # Static assets (images, videos)
```

## 🚀 Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (for dependencies)

### Step 1: Clone the Repository
```bash
git clone https://github.com/your-username/taabia.git
cd taabia
```

### Step 2: Database Setup
1. Create a MySQL database named `taabia_skills`
2. Import the database schema:
```bash
mysql -u root -p taabia_skills < database/schema.sql
```

### Step 3: Configuration
1. Update database connection in `includes/db.php`:
```php
$host = 'localhost';
$db   = 'taabia_skills';
$user = 'your_username';
$pass = 'your_password';
```

2. Configure payment gateway credentials in `Payment/FlutterwaveGateway.php`

### Step 4: File Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/lessons/
```

### Step 5: Web Server Configuration
Ensure your web server points to the project root directory and has PHP support enabled.

## 🔐 Default Admin Account

After installation, you can log in with:
- **Email**: admin@taabia.com
- **Password**: admin123

**⚠️ Important**: Change the default admin password immediately after first login.

## 📊 Database Schema

### Core Tables
- `users` - Multi-role user management
- `courses` - Course information and metadata
- `course_contents` - Individual lessons and materials
- `products` - Vendor product catalog
- `orders` - Order management
- `transactions` - Payment tracking
- `events` - Event management
- `messages` - Internal messaging system

### Relationships
- Users can have multiple roles (admin, instructor, student, vendor)
- Instructors can create multiple courses
- Students can enroll in multiple courses
- Vendors can list multiple products
- Orders can contain multiple items (courses/products)

## 💳 Payment Integration

### Supported Gateways
1. **Flutterwave** (Primary - African markets)
2. **Stripe** (International)
3. **Paystack** (West Africa)
4. **Mobile Money** (Local integration)

### Payment Flow
1. User selects items (courses/products)
2. System generates transaction reference
3. Payment gateway initialization
4. User completes payment
5. Webhook verification
6. Order fulfillment

## 🎨 User Interface

### Design Principles
- **Mobile-first responsive design**
- **Intuitive navigation**
- **Consistent color scheme** (Green theme: #00796b)
- **Accessibility compliance**
- **Fast loading times**

### Key Pages
- **Landing Page**: Platform overview and course showcase
- **Dashboard**: Role-specific analytics and quick actions
- **Course Management**: CRUD operations for instructors
- **Product Catalog**: Vendor product management
- **Learning Interface**: Student course access

## 🔒 Security Features

### Authentication & Authorization
- Session-based authentication
- Role-based access control (RBAC)
- Password hashing with bcrypt
- CSRF protection
- SQL injection prevention

### Data Protection
- Input sanitization
- File upload validation
- Secure file storage
- HTTPS enforcement (recommended)

## 📈 Analytics & Reporting

### Admin Analytics
- User registration trends
- Course enrollment statistics
- Revenue tracking
- Platform usage metrics

### Instructor Analytics
- Course performance metrics
- Student engagement data
- Earnings reports
- Student progress tracking

### Vendor Analytics
- Product sales performance
- Inventory management
- Customer insights
- Revenue optimization

## 🚀 Deployment

### Production Checklist
- [ ] SSL certificate installation
- [ ] Database optimization
- [ ] File upload limits configuration
- [ ] Error logging setup
- [ ] Backup strategy implementation
- [ ] Performance monitoring
- [ ] Security audit

### Environment Variables
```bash
# Database
DB_HOST=localhost
DB_NAME=taabia_skills
DB_USER=your_username
DB_PASS=your_password

# Payment Gateway
FLUTTERWAVE_PUBLIC_KEY=your_public_key
FLUTTERWAVE_SECRET_KEY=your_secret_key
FLUTTERWAVE_ENCRYPTION_KEY=your_encryption_key
```

## 🤝 Contributing

### Development Guidelines
1. Fork the repository
2. Create a feature branch
3. Follow coding standards (PSR-12)
4. Write comprehensive tests
5. Submit a pull request

### Code Standards
- Use PSR-12 coding standards
- Include proper documentation
- Write meaningful commit messages
- Test thoroughly before submission

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Documentation
- [User Manual](docs/user-manual.md)
- [API Documentation](docs/api.md)
- [Developer Guide](docs/developer-guide.md)

### Contact
- **Email**: support@taabia.com
- **Website**: https://taabia.com
- **Documentation**: https://docs.taabia.com

## 🎯 Roadmap

### Phase 1 (Current)
- ✅ Multi-role authentication
- ✅ Course management system
- ✅ Product catalog
- ✅ Payment integration
- ✅ Basic analytics

### Phase 2 (Planned)
- 🔄 Advanced analytics dashboard
- 🔄 Mobile app development
- 🔄 AI-powered recommendations
- 🔄 Advanced payment methods
- 🔄 Multi-language support

### Phase 3 (Future)
- 📋 Blockchain certification
- 📋 VR/AR learning experiences
- 📋 Advanced AI features
- 📋 International expansion

## 🙏 Acknowledgments

- **Flutterwave** for payment gateway integration
- **Font Awesome** for icons
- **Inter Font** for typography
- **Chart.js** for analytics visualization

---

**Built with ❤️ for African entrepreneurs and educators**

*TaaBia - Empowering skills, enabling commerce, connecting communities.*