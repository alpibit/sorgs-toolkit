# Sorgs Uptime Monitor

Sorgs is a simple, lightweight uptime monitoring system built with PHP. It monitors the availability and performance of websites and web services with smart retry logic, multi-channel notifications, and SSL certificate monitoring capabilities.

## Features

- **Uptime Monitoring**: Check website availability with configurable intervals
- **Smart Retry Logic**: Exponential backoff and multiple retry attempts
- **SSL Certificate Monitoring**: Track certificate expiry dates and receive timely alerts
- **Multi-Channel Notifications**: Email and Telegram alert delivery
- **Keyword Verification**: Ensure specific content appears in responses
- **HTTP Status Validation**: Verify expected status codes
- **Dashboard**: Real-time statistics and monitoring overview
- **Filter Views**: Easily view monitors by status or SSL expiration

## Requirements

- PHP 8.0 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- PHP Extensions:
  - PDO and PDO_MySQL
  - cURL
  - OpenSSL
  - Socket (for SSL certificate checking)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/alpibit/sorgs-toolkit.git
```

2. Run the installer by visiting:
```
http://yourdomain.com/install.php
```

3. Configure your settings:
   - Database connection
   - Admin account
   - SMTP settings for email alerts (tested with Gmail, but other providers should work)
   - Telegram bot token (optional)

4. Set up the cron job for automated checking:
```bash
* * * * * php /path/to/sorgs/cron.php
```

## Configuration

### Email Notifications

- Configure SMTP settings in the admin panel
- Add notification email addresses to individual monitors
- Supports both TLS and SSL secure connections

### Telegram Notifications

- Create a Telegram bot via BotFather
- Configure the bot token in settings
- Add chat IDs to monitors for notifications
- Test the connection using the built-in test feature

### Monitor Settings

- **URL**: The endpoint to monitor
- **Check Interval**: Time between checks (in seconds)
- **Expected Status Code**: Default 200
- **Expected Keyword**: Optional text to verify in response
- **Notification Settings**: Emails and Telegram chat IDs
- **SSL Monitoring**: Automatically enabled for HTTPS URLs

## SSL Certificate Monitoring

Sorgs automatically monitors SSL certificates for HTTPS websites with the following features:

- **Certificate Expiry Detection**: Track when certificates will expire
- **Early Warnings**: Get notifications 30 days before expiration 
- **Critical Alerts**: Receive elevated warnings when certificates are 7 days from expiry
- **Dashboard Indicators**: Color-coded visual indicators of certificate status
- **Filtering**: View all monitors with expiring certificates in one place

## Dashboard

The dashboard provides:

- Total number of monitors and their status (up/down/unknown)
- Average response time of your monitored sites
- SSL certificate status summary
- Color-coded status indicators
- Filtering options for different views (all/down/SSL expiring)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## Security

Found a vulnerability? Please do NOT open an issue. Email `security@pirags.com` instead.

## License

MIT License
Copyright (c) 2025 Aleksandrs Pirags