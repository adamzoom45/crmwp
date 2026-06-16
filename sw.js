/**
 * Service Worker для Push уведомлений
 * 
 * @package AKPP45_CRM
 */

self.addEventListener('push', function(event) {
    var data = {};
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = {
                title: 'АКПП45 CRM',
                body: event.data.text()
            };
        }
    }
    
    var title = data.title || 'АКПП45 CRM';
    var options = {
        body: data.body || 'Новое уведомление',
        icon: data.icon || '/favicon.ico',
        badge: data.badge || '/favicon.ico',
        tag: data.tag || 'default',
        data: {
            url: data.click_action || data.url || '/crm-profile',
            type: data.type || 'notification'
        },
        requireInteraction: data.requireInteraction || false,
        vibrate: data.vibrate || [200, 100, 200],
        actions: data.actions || [
            {
                action: 'open',
                title: 'Открыть'
            },
            {
                action: 'close',
                title: 'Закрыть'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    var url = event.notification.data?.url || '/crm-profile';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                for (var i = 0; i < clientList.length; i++) {
                    var client = clientList[i];
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

self.addEventListener('notificationclose', function(event) {
    console.log('Уведомление закрыто:', event.notification.tag);
});

self.addEventListener('install', function(event) {
    console.log('Service Worker установлен');
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker активирован');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function(event) {
    // Здесь можно добавить кэширование, если необходимо
    event.respondWith(fetch(event.request));
});
