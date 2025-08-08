# 🏠 Homepage Dashboard

A sleek, modern server homepage and dashboard with system monitoring, Docker container management, and customizable app shortcuts. Built with vanilla PHP, JavaScript, and CSS - no frameworks required!

![Dashboard Preview](https://img.shields.io/badge/Status-Active-brightgreen)
![PHP](https://img.shields.io/badge/PHP-8.0+-blue)
![Docker](https://img.shields.io/badge/Docker-Supported-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## ✨ Features

### 🎯 **Smart Dashboard**
- **Customizable Search** - Google, DuckDuckGo, Brave, or custom search engines
- **App Shortcuts** - Drag-and-drop app tiles with emoji icons
- **Dark/Light Theme** - Automatic theme switching with smooth transitions
- **Responsive Design** - Works perfectly on desktop, tablet, and mobile

### 📊 **System Monitoring**
- **Real-time Stats** - CPU temperature, usage, memory, disk space
- **Security Monitoring** - Fail2ban status and blocked attempts
- **Uptime Tracking** - System uptime and health indicators
- **Network Monitoring** - Internet speed tests and service status

### 🐳 **Docker Management**
- **Container Overview** - Status, resource usage, and uptime
- **Quick Actions** - Start, stop, restart containers with one click
- **Log Viewing** - Real-time container logs in browser
- **Deploy Containers** - Simple Docker run or Docker Compose deployment
- **Advanced Operations** - Remove containers and cleanup images/volumes

### 🔐 **Security Features**
- **Session-based Authentication** - Secure login for sensitive operations
- **Permission Controls** - Protected Docker operations and system access
- **Audit Logging** - Track all administrative actions
- **Auto-logout** - Sessions expire automatically for security

## 🚀 Quick Start

### Prerequisites
- **PHP 8.0+** with web server (Apache/Nginx)
- **Docker** (for container management features)
- **Ubuntu/Debian** system (for system monitoring)
- **sudo privileges** (for Docker and system commands)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/ajh151/homepage.git
   cd homepage
   ```

2. **Set up web server**
   ```bash
   # For Apache
   sudo cp -r . /var/www/html/homepage/
   sudo chown -R www-data:www-data /var/www/html/homepage/
   
   # For Nginx
   sudo cp -r . /var/www/homepage/
   sudo chown -R www-data:www-data /var/www/homepage/
   ```

3. **Configure sudo permissions**
   ```bash
   # Add to /etc/sudoers.d/homepage
   echo "www-data ALL=(ALL) NOPASSWD: /snap/bin/docker, /usr/bin/docker, /usr/bin/fail2ban-client" | sudo tee /etc/sudoers.d/homepage
   ```

4. **Set up data directory**
   ```bash
   sudo mkdir -p /var/www/homepage/data
   sudo chown www-data:www-data /var/www/homepage/data
   ```

5. **Access your dashboard**
   Open `http://your-server-ip/homepage` in your browser

## 📁 Project Structure

```
homepage/
├── 📄 index.html              # Main dashboard interface
├── 📁 api/                    # Backend PHP APIs
│   ├── 🔐 auth.php           # Authentication system
│   ├── 🐳 docker.php         # Docker management
│   ├── 📊 stats.php          # System statistics
│   ├── 🌐 network.php        # Network monitoring
│   └── 💾 storage.php        # Data persistence
├── 📁 data/                   # User data storage
│   ├── ⚙️ settings.json      # Dashboard settings
│   └── 📱 apps.json          # App shortcuts
└── 📄 README.md              # This file
```

## 🔧 Configuration

### System Monitoring Setup

1. **CPU Temperature** (Raspberry Pi/ARM systems):
   ```bash
   # Ensure thermal sensors are accessible
   ls /sys/class/thermal/thermal_zone*/temp
   ```

2. **Fail2ban Integration**:
   ```bash
   # Install fail2ban if not present
   sudo apt install fail2ban
   
   # Verify service is running
   sudo systemctl status fail2ban
   ```

3. **Docker Access**:
   ```bash
   # Add web server user to docker group
   sudo usermod -aG docker www-data
   
   # Or use snap docker (recommended)
   sudo snap install docker
   ```

### Network Monitoring Setup

Configure services to monitor in `api/network.php`:

```php
$services = [
    'Plex' => [
        'host' => 'localhost',
        'port' => 32400,
        'url' => 'http://localhost:32400'
    ],
    'Your Service' => [
        'host' => 'your-ip',
        'port' => 80,
        'url' => 'http://your-service'
    ]
];
```

## 🎨 Customization

### Adding Custom Apps
1. Click the **"Add"** button in the Apps section
2. Fill in name, URL, and choose an emoji icon
3. Drag and drop to reorder your apps

### Search Engine Configuration
1. Click the **settings gear** (⚙️) in the header
2. Choose from predefined engines or add custom URL
3. Use `{query}` placeholder for custom search URLs

### Theme Customization
The CSS uses custom properties for easy theming:

```css
:root {
    --bg-primary: #111111;
    --text-primary: #ffffff;
    --accent-color: #4a9eff;
    /* ... more variables */
}
```

## 🔐 Security Notes

### Authentication
- Uses system user authentication (PAM)
- Sessions expire after 30 minutes of inactivity
- No passwords stored - verified against system users

### Docker Security
- Requires authentication for container deployment
- Audit logs for all Docker operations
- Separate permissions for read vs. write operations

### Recommendations
- Use HTTPS in production environments
- Limit access via firewall rules
- Regular security updates for system packages
- Monitor audit logs for suspicious activity

## 🐛 Troubleshooting

### Common Issues

**Docker commands fail**:
```bash
# Check sudo permissions
sudo -u www-data sudo docker ps

# Verify docker installation
which docker
docker --version
```

**System stats not showing**:
```bash
# Check file permissions
ls -la /sys/class/thermal/
ls -la /proc/loadavg

# Verify web server user can read system files
sudo -u www-data cat /proc/loadavg
```

**Authentication not working**:
```bash
# Check system users
cat /etc/passwd | grep your-username

# Test PAM authentication
sudo -u www-data su your-username
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built with vanilla web technologies for maximum compatibility
- Inspired by modern dashboard designs and server management tools
- Uses system-native authentication for security

---

**🌟 Star this repo if you find it useful!**

For questions, issues, or feature requests, please open an issue on GitHub.
