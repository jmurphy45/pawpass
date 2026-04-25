self.addEventListener('push', function (event) {
    if (!event.data) return;

    const payload = event.data.json();
    const title = payload.title || 'PawPass';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        badge: '/favicon.ico',
        data: { actionUrl: payload.actionUrl || '/', type: payload.type || '' },
        tag: payload.type || 'pawpass',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const url = event.notification.data?.actionUrl || '/';

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (windowClients) {
                for (const client of windowClients) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            }),
    );
});
