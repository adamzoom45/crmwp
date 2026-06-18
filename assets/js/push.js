/**
 * CRM АКПП45 - Push уведомления (клиентская часть)
 * 
 * @package AKPP45_CRM
 */

(function() {
    'use strict';
    
    var AKPP_Push = {
        
        // Публичный VAPID ключ (замените на свой при регистрации в Firebase)
        vapidPublicKey: 'YOUR_VAPID_PUBLIC_KEY',
        
        // Service Worker URL
        swUrl: '/sw.js',
        
        // Инициализация
        init: function() {
            if (!this.isSupported()) {
                console.log('Push уведомления не поддерживаются браузером');
                return;
            }
            
            this.checkPermission();
            this.registerServiceWorker();
        },
        
        // Проверка поддержки
        isSupported: function() {
            return 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
        },
        
        // Проверка разрешения
        checkPermission: function() {
            if (Notification.permission === 'granted') {
                this.registerServiceWorker();
            } else if (Notification.permission !== 'denied') {
                this.requestPermission();
            }
        },
        
        // Запрос разрешения
        requestPermission: function() {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    AKPP_Push.registerServiceWorker();
                }
            });
        },
        
        // Регистрация Service Worker
        registerServiceWorker: function() {
            var self = this;
            
            navigator.serviceWorker.register(this.swUrl)
                .then(function(registration) {
                    console.log('Service Worker зарегистрирован:', registration);
                    self.registration = registration;
                    self.subscribeToPush(registration);
                })
                .catch(function(error) {
                    console.error('Ошибка регистрации Service Worker:', error);
                });
        },
        
        // Подписка на push уведомления
        subscribeToPush: function(registration) {
            var self = this;
            
            registration.pushManager.getSubscription()
                .then(function(subscription) {
                    if (subscription) {
                        console.log('Существующая подписка найдена');
                        self.saveSubscription(subscription);
                        return;
                    }
                    
                    // Создаем новую подписку
                    var options = {
                        userVisibleOnly: true,
                        applicationServerKey: self.urlBase64ToUint8Array(self.vapidPublicKey)
                    };
                    
                    return registration.pushManager.subscribe(options);
                })
                .then(function(subscription) {
                    if (subscription) {
                        console.log('Новая подписка создана:', subscription);
                        self.saveSubscription(subscription);
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка подписки на push:', error);
                });
        },
        
        // Сохранение подписки на сервере
        saveSubscription: function(subscription) {
            var token = JSON.stringify(subscription);
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'akpp_save_push_token',
                    token: token,
                    device_type: 'web',
                    nonce: akpp_push_nonce || ''
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    console.log('Push токен сохранен на сервере');
                } else {
                    console.error('Ошибка сохранения токена:', data.data.message);
                }
            })
            .catch(function(error) {
                console.error('Ошибка отправки токена:', error);
            });
        },
        
        // Удаление подписки
        unsubscribe: function() {
            var self = this;
            
            if (!this.registration) {
                return;
            }
            
            this.registration.pushManager.getSubscription()
                .then(function(subscription) {
                    if (subscription) {
                        // Удаляем подписку
                        var token = JSON.stringify(subscription);
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'akpp_delete_push_token',
                                token: token,
                                nonce: akpp_push_nonce || ''
                            })
                        });
                        
                        return subscription.unsubscribe();
                    }
                })
                .then(function() {
                    console.log('Отписка от push уведомлений выполнена');
                })
                .catch(function(error) {
                    console.error('Ошибка отписки:', error);
                });
        },
        
        // Конвертация base64 в Uint8Array (для VAPID ключа)
        urlBase64ToUint8Array: function(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            
            var rawData = window.atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            
            for (var i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },
        
        // Показ уведомления (для тестирования)
        showTestNotification: function() {
            if (Notification.permission === 'granted') {
                new Notification('АКПП45 CRM', {
                    body: 'Тестовое уведомление. Если вы видите это сообщение, push уведомления работают!',
                    icon: '/favicon.ico',
                    tag: 'test',
                    requireInteraction: false
                });
            }
        }
    };
    
    // Инициализация после загрузки страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            AKPP_Push.init();
        });
    } else {
        AKPP_Push.init();
    }
    
    // Экспортируем в глобальную область
    window.AKPP_Push = AKPP_Push;
    
})();
