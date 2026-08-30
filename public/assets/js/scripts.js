document.addEventListener('submit', function (event) {
    const form = event.target.closest('form[data-confirm]');

    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('#test-telegram-btn');

    if (button) {
        testTelegramConnection(button);
    }
});

function testTelegramConnection(button) {
    const formData = new FormData();
    formData.append('csrf_token', button.dataset.csrf || '');

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
