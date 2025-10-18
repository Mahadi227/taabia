# Community System Implementation

## Overview

A comprehensive mini social community system integrated into the TaaBia Skills & Market platform. This system allows users to create, join, and participate in communities with features like posts, comments, likes, and member management.

## Features

### Core Functionality

- **Community Creation**: Admins and instructors can create communities
- **Community Joining**: Students and vendors can join public communities
- **Post System**: Create and manage posts with different types (text, image, video, link)
- **Comment System**: Comment on posts with nested replies
- **Like System**: Like posts and comments
- **Member Management**: Role-based permissions (admin, moderator, member)
- **Privacy Controls**: Public, private, and invite-only communities
- **Categories**: Organize communities by categories
- **Notifications**: Real-time notifications for community activities

### User Roles & Permissions

- **Admin**: Full control over all communities
- **Instructor**: Can create and manage communities
- **Student/Vendor**: Can join communities and participate (with optional community creation)

## File Structure

### Database

- `database/community_system.sql` - Complete database schema
- `setup_community_system.php` - Database setup script

### Admin Interface

- `admin/communities.php` - Community management dashboard
- `admin/community_details.php` - Detailed community management

### Public Interface

- `public/communities.php` - Browse and join communities
- `public/community.php` - Individual community view
- `public/community_create.php` - Create new community
- `public/community_post.php` - Individual post view

### API

- `api/community_actions.php` - AJAX endpoints for likes, comments, etc.

### Helper Files

- `includes/community_functions.php` - Helper functions
- `lang/community_en.php` - English translations
- `lang/community_fr.php` - French translations

### Test Files

- `test_community_system.php` - System test script

## Database Tables

### Core Tables

- `communities` - Community information
- `community_members` - User memberships
- `community_posts` - Community posts
- `post_comments` - Post comments
- `post_likes` - Post likes
- `comment_likes` - Comment likes

### Supporting Tables

- `community_categories` - Community categories
- `community_category_assignments` - Community-category relationships
- `community_invitations` - Community invitations
- `community_notifications` - User notifications

## Installation

### 1. Database Setup

```bash
php setup_community_system.php
```

### 2. File Integration

Ensure all files are placed in the correct directories within your TaaBia project.

### 3. Navigation Updates

Add community links to your navigation menus:

```php
// For admin/instructor sidebar
<li><a href="communities.php"><i class="fas fa-users"></i> Communities</a></li>

// For public navigation
<li><a href="communities.php"><i class="fas fa-users"></i> Communities</a></li>
```

### 4. Language Integration

Include community language files in your i18n system:

```php
// In your language handler
$community_lang = include 'lang/community_' . $current_language . '.php';
$translations = array_merge($translations, $community_lang);
```

## Usage

### For Admins/Instructors

1. Access community management via admin dashboard
2. Create new communities with categories and privacy settings
3. Manage community members and their roles
4. Moderate content and manage posts

### For Students/Vendors

1. Browse available communities
2. Join communities of interest
3. Create posts and engage in discussions
4. Like and comment on posts

## API Endpoints

### Community Actions (`api/community_actions.php`)

- `like_post` - Like/unlike a post
- `add_comment` - Add a comment to a post
- `get_comments` - Get post comments
- `like_comment` - Like/unlike a comment
- `delete_post` - Delete a post
- `pin_post` - Pin/unpin a post

## Configuration

### System Settings

Add these settings to your `system_settings` table:

```sql
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('allow_student_communities', '1', 'Allow students and vendors to create communities'),
('max_communities_per_user', '5', 'Maximum communities a user can create'),
('max_posts_per_day', '10', 'Maximum posts per user per day');
```

### Community Categories

Default categories are automatically created:

- Formation & Éducation
- Technologie
- Business & Entrepreneuriat
- Développement Personnel
- Réseautage
- Général

## Testing

Run the test script to verify installation:

```bash
php test_community_system.php
```

This will test:

- Database connectivity
- Community functions
- User permissions
- Language support
- File structure

## Security Features

- **Input Sanitization**: All user inputs are sanitized
- **SQL Injection Protection**: Prepared statements used throughout
- **XSS Protection**: HTML entities escaped
- **Role-based Access**: Proper permission checks
- **CSRF Protection**: Form tokens (recommended to implement)

## Performance Considerations

- **Database Indexes**: Optimized for common queries
- **Pagination**: Implemented for posts and comments
- **Caching**: Consider implementing for frequently accessed data
- **File Uploads**: Proper validation and storage

## Customization

### Adding New Post Types

1. Update the `post_type` enum in the database
2. Modify the post creation form
3. Update the display logic in `community.php`

### Custom Categories

Add new categories to the `community_categories` table:

```sql
INSERT INTO community_categories (name, description, color, icon, sort_order) VALUES
('Your Category', 'Description', '#ff0000', 'fas fa-icon', 10);
```

### Custom Permissions

Modify the `get_community_permissions()` function to add custom permission logic.

## Troubleshooting

### Common Issues

1. **Database Connection**: Ensure database credentials are correct
2. **File Permissions**: Check that web server can read all files
3. **Language Files**: Verify language files are properly included
4. **Session Issues**: Ensure sessions are properly started

### Debug Mode

Enable debug mode in your configuration to see detailed error messages.

## Support

For issues or questions:

1. Check the test script output
2. Verify database setup
3. Check file permissions
4. Review error logs

## Future Enhancements

Potential features to add:

- Real-time notifications
- File attachments
- Polls and surveys
- Community events
- Advanced moderation tools
- Mobile app integration
- Analytics dashboard

## License

This community system is part of the TaaBia Skills & Market platform and follows the same licensing terms.






