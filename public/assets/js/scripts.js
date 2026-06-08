function testTelegramConnection(button) {
    const csrfToken = button && button.dataset ? button.dataset.csrf : '';
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);

    fetch('test_telegram.php', {
        method: 'POST',
        body: formData
    })
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
