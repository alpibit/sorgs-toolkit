function testTelegramConnection() {
    fetch('test_telegram.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Telegram test message sent successfully!');
            } else {
                alert('Failed to send Telegram test message: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error testing Telegram connection: ' + error);
        });
}
