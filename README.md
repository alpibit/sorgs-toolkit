# Sorgs Uptime Monitor
Sorgs is a simple, lightweight uptime monitoring system built with PHP. It allows you to monitor the availability and performance of websites and web services with smart retry logic and multi-channel notifications.

## Requirements
- PHP 8.0 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
+ PHP Extensions:
  - PDO and PDO_MySQL
  - cURL
  - OpenSSL

## Installation
+ 1. Clone the repository:
```bash
git clone https://github.com/alpibit/sorgs-toolkit.git
```
+ 2. Run the installer by visiting:
```
http://yourdomain.com/install.php
```
+ 3. Configure your settings:
- Database connection
- Admin account
- SMTP settings for email alerts (tested with Gmail, but other providers should work)
- Telegram bot token (optional)
+ 4. Set up the cron job:
```bash
* * * * * php /path/to/sorgs/cron.php
```


## Configuration

### Email Notifications
- Configure SMTP settings in the admin panel
- Add notification email addresses to individual monitors

### Telegram Notifications
- Create a Telegram bot via BotFather
- Configure the bot token in settings
- Add chat IDs to monitors for notifications
- Test the connection using the built-in test feature

### Monitor Settings
- URL: The endpoint to monitor
- Check Interval: Time between checks (in seconds)
- Expected Status Code: Default 200
- Expected Keyword: Optional text to verify in response
- Notification Settings: Emails and Telegram chat IDs


## Contributing
Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.


## Security
Found a vulnerability? Please do NOT open an issue. Email `security@pirags.com` instead.


## License
MIT License
Copyright (c) 2025 Aleksandrs Pirags