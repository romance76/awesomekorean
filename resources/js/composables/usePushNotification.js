const VAPID_PUBLIC_KEY = 'BLVT7DZrnaD96Ygh4FlIaUjcexV0xFOkKs91apzUUxZQxzO5zS06oIW18AVj8vnKuzH_Kev3zYGGL1g7pdsC438';

export function usePushNotification() {
    async function subscribe(userId) {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push notifications not supported');
            return false;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
            }

            // 서버에 구독 정보 저장
            await fetch('/api/socket/push-subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userId,
                    subscription: subscription.toJSON()
                })
            });

            console.log('Push subscription saved for user', userId);
            return true;
        } catch (err) {
            console.error('Push subscription failed:', err);
            return false;
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function requestPermission() {
        if (!('Notification' in window)) return 'denied';
        return await Notification.requestPermission();
    }

    return { subscribe, requestPermission };
}
