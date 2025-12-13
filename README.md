# Tachyon - Advanced ToDo Application

![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Quill.js](https://img.shields.io/badge/Quill.js-25B0E4?style=for-the-badge&logo=quill&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)

[![Live Demo](https://img.shields.io/badge/Live_Demo-Visit_Tachyon-4CAF50?style=for-the-badge)](https://tachyon.rf.gd/)

> A feature-rich ToDo application built with HTML, CSS, JavaScript, PHP, and MySQL database integration. Hosted on InfinityFree with custom authentication system.

## ✨ Features

- **🔐 Secure Authentication**: Robust login and registration system with password hashing
- **💾 Persistent Storage**: MySQL database for reliable todo item storage
- **📱 Responsive Design**: Clean, modern interface accessible on all devices
- **⚡ Dynamic Interactions**: Real-time todo management powered by JavaScript
- **🛡️ Security**: Protection against SQL injection and secure session management
- **� Rich Text Editing**: Google Notes-like functionality empowered by Quill.js
- **�🚀 Deployed Solution**: Actively hosted on InfinityFree platform

## 🛠️ Technologies Used

| Technology | Purpose |
|------------|---------|
| **HTML5** | Structuring and content |
| **CSS3** | Styling and responsive layout |
| **JavaScript (ES6+)** | Dynamic functionality & interactivity |
| **PHP 7+** | Server-side processing |
| **MySQL** | Database management |
| **Quill.js** | Rich text editor |
| **InfinityFree** | Hosting platform |
| **Git** | Version control |

## 📁 Project Structure

```
Tachyon-Todo-App/
├── index.php              # Main landing page
├── welcome.php            # User dashboard
├── register.php           # User registration
├── login.php              # User login
├── dashboard.php          # Todo dashboard
├── db_connect.php         # Database connection
├── init_database.php      # Database initialization
├── fetch_todos.php        # API: Fetch todos
├── add_todo.php           # API: Add new todo
├── complete_todo.php      # API: Mark todo as complete
├── update_todo.php        # API: Update todo
├── delete_todo.php        # API: Delete todo
├── login_process.php      # Handle login logic
├── register_process.php   # Handle registration logic
├── logout.php             # Handle logout
├── css/                   # Stylesheets
│   └── style.css
├── js/                    # JavaScript files
│   └── script.js
└── README.md              # Documentation
```

## 🚀 Quick Setup

### Prerequisites
- Web server with PHP support (Apache/Nginx)
- MySQL database
- Web browser

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/Tachyon-Todo-App.git
   cd Tachyon-Todo-App
   ```

2. **Set up your local server** (with Apache and PHP support)

3. **Configure Database**
   - Create a MySQL database
   - Import the schema from `init_database.php` or create the tables manually:
     ```sql
     CREATE TABLE users (
         id INT AUTO_INCREMENT PRIMARY KEY,
         username VARCHAR(50) UNIQUE NOT NULL,
         password VARCHAR(255) NOT NULL,
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     );
     
     CREATE TABLE todos (
         id INT AUTO_INCREMENT PRIMARY KEY,
         user_id INT NOT NULL,
         title VARCHAR(255) NOT NULL,
         completed BOOLEAN DEFAULT FALSE,
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         FOREIGN KEY (user_id) REFERENCES users(id)
     );
     ```

4. **Update Configuration**
   Modify `db_connect.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_username');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'tachyon_todo_app');
   ```

5. **Access the Application**
   Navigate to your web server's document root in a browser

## 🌐 Live Demo

Experience Tachyon in action: [https://tachyon.rf.gd/](https://tachyon.rf.gd/)

## 🛡️ Security Features

- Password hashing with PHP's `password_hash()`
- Input sanitization to prevent XSS attacks
- SQL injection prevention through prepared statements
- Secure session management
- Proper authentication checks on all protected pages

## 🤝 Contributing

Contributions are welcome! Here's how you can contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📞 Contact

Tachyon ToDo Application
- Hosted at: [https://tachyon.rf.gd/](https://tachyon.rf.gd/)
- Built with ❤️ using modern web technologies

## ⭐ Support

If you find this project helpful, please give it a star! It helps others discover the project and motivates continued development.