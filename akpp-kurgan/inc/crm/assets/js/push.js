/**
 * АКПП45 CRM - Push Notifications JavaScript (FCM)
 * Запрашивает разрешение на уведомления, получает токен и сохраняет его на сервере.
 */

jQuery(document).ready(function($) {
    'use strict';

    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // Проверяем, поддерживаются ли уведомления в браузере
    if (!('Notification' in window)) {
        console.log('Этот браузер не поддерживает веб-уведомления.');
        return;
    }

    // Инициализация Push-уведомлений
    function initPushNotifications() {
        // Проверяем, инициализирован ли Firebase (должен быть подключен в header/footer темы)
        if (typeof firebase === 'undefined' || typeof firebase.messaging === 'undefined') {
            console.warn('Firebase SDK не найден. Push-уведомления отключены.');
            return;
        }

        // Инициализация Firebase Messaging (замените на ваши реальные данные из Firebase Console)
        // const firebaseConfig = {
        //     apiKey: "YOUR_API_KEY",
        //     authDomain: "YOUR_PROJECT.firebaseapp.com",
        //     projectId: "YOUR_PROJECT_ID",
        //     storageBucket: "YOUR_PROJECT.appspot.com",
        //     messagingSenderId: "YOUR_SENDER_ID",
        //     appId: "YOUR_APP_ID"
        // };
        // firebase.initializeApp(firebaseConfig);

        const messaging = firebase.messaging();

        // Запрос разрешения на уведомления
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                console.log('Разрешение на уведомления получено.');
                getAndSaveToken(messaging);
            } else {
                console.log('Пользователь запретил показ уведомлений.');
            }
        }).catch(function(err) {
            console.error('Ошибка при запросе разрешения:', err);
        });
    }

    // Получение токена и отправка на сервер
    function getAndSaveToken(messaging) {
        messaging.getToken({ vapidKey: 'YOUR_PUBLIC_VAPID_KEY' }) // Замените на ваш VAPID ключ из Firebase
            .then(function(currentToken) {
                if (currentToken) {
                    console.log('FCM Token получен:', currentToken);
                    saveTokenToServer(currentToken, 'web');
                } else {
                    console.log('Токен не получен. Проверьте настройки Firebase.');
                }
            })
            .catch(function(err) {
                console.error('Ошибка при получении токена FCM:', err);
            });
    }

    // Отправка токена на сервер WordPress через AJAX
    function saveTokenToServer(token, deviceType) {
        $.post(ajaxUrl, {
            action: 'akpp_save_push_token',
            nonce: nonce,
            token: token,
            device_type: deviceType
        }, function(response) {
            if (response.success) {
                console.log('Токен успешно сохранен на сервере.');
            } else {
                console.error('Ошибка сохранения токена:', response.data.message);
            }
        }).fail(function() {
            console.error('Ошибка сети при сохранении токена.');
        });
    }

    // Обработка входящих сообщений, когда приложение на переднем плане
    if (typeof firebase !== 'undefined' && typeof firebase.messaging !== 'undefined') {
        const messaging = firebase.messaging();
        messaging.onMessage(function(payload) {
            console.log('Получено сообщение на переднем плане:', payload);
            
            // Показываем нативное уведомление браузера
            if (Notification.permission === 'granted') {
                new Notification(payload.notification.title, {
                    body: payload.notification.body,
                    icon: '/wp-content/themes/akpp-kurgan/assets/images/icon-192x192.png' // Путь к иконке
                });
            }
        });
    }

    // Запуск инициализации при загрузке страницы (для авторизованных пользователей)
    if (typeof isUserLoggedIn !== 'undefined' && isUserLoggedIn) {
        // Небольшая задержка, чтобы убедиться, что Firebase SDK полностью загрузился
        setTimeout(initPushNotifications, 1000);
    }

});
