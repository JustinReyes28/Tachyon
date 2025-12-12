# Tachyon - ToDo Website

A feature-rich ToDo application built with HTML, CSS, JavaScript, PHP, and MySQL database integration. Hosted on InfinityFree with custom authentication system.

## Features

- **User Authentication**: Secure login and registration system built with custom PHP and MySQL
- **Database Integration**: Persistent storage using MySQL for todo items
- **Responsive Design**: Clean and modern interface using HTML and CSS
- **Dynamic Functionality**: Interactive todo management with JavaScript
- **Secure Hosting**: Deployed on InfinityFree platform

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 7+
- **Database**: MySQL
- **Hosting**: InfinityFree
- **Authentication**: Custom PHP + MySQL implementation
- **Version Control**: Git

## Project Structure

```
todo-app/
├── index.html          # Main entry point
├── css/               # Stylesheets
│   └── style.css
├── js/                # JavaScript files
│   └── script.js
├── php/               # PHP backend files
│   ├── config.php     # Database configuration
│   ├── auth.php       # Authentication functions
│   ├── login.php      # Login processing
│   ├── register.php   # Registration processing
│   └── api/           # API endpoints
├── sql/               # Database schema
│   └── schema.sql
└── README.md          # This file
```
## Setup Instructions

### Local Development

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/tachyon.git
   cd tachyon
   ```

2. Set up your local server (Apache with PHP support)

3. Create a MySQL database and import the schema from `sql/schema.sql`

4. Update database configuration in `php/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_username');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'tachyondb');
   ```

5. Access the application through your web browser

### InfinityFree Deployment

1. Sign up for an account at [InfinityFree](https://infinityfree.net/)

2. Create a new website and upload all project files

3. Create a MySQL database through InfinityFree's control panel

4. Import the database schema

5. Update `php/config.php` with InfinityFree's database credentials

## Authentication System

The custom PHP + MySQL authentication includes:

- User registration with password hashing
- Secure login with session management
- Password validation and security measures
- Session timeout for security
- Input sanitization to prevent SQL injection

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

Tachyon ToDo Application
- Built with ❤️ using web technologies
- Designed for simplicity and efficiency
