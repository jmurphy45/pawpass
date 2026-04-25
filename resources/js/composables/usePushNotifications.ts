import { ref, computed } from 'vue';
import axios from 'axios';

const isSupported = computed(
    () => typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window,
);

const permission = ref<NotificationPermission>(
    typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'default',
);

const isSubscribed = ref(false);

async function getRegistration(): Promise<ServiceWorkerRegistration | null> {
    if (!isSupported.value) return null;
    try {
        return await navigator.serviceWorker.ready;
    } catch {
        return null;
    }
}

async function checkSubscription(): Promise<void> {
    const reg = await getRegistration();
    if (!reg) return;
    const sub = await reg.pushManager.getSubscription();
    isSubscribed.value = !!sub;
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

async function subscribe(vapidPublicKey: string, apiPrefix: 'portal' | 'admin' = 'portal'): Promise<void> {
    if (!isSupported.value) return;

    const perm = await Notification.requestPermission();
    permission.value = perm;
    if (perm !== 'granted') return;

    await navigator.serviceWorker.register('/sw.js');
    const reg = await navigator.serviceWorker.ready;

    const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    const json = sub.toJSON();
    await axios.post(`/api/${apiPrefix}/v1/push-subscriptions`, {
        endpoint: sub.endpoint,
        p256dh: json.keys?.p256dh,
        auth: json.keys?.auth,
        user_agent: navigator.userAgent,
    });

    isSubscribed.value = true;
}

async function unsubscribe(apiPrefix: 'portal' | 'admin' = 'portal'): Promise<void> {
    const reg = await getRegistration();
    if (!reg) return;

    const sub = await reg.pushManager.getSubscription();
    if (!sub) return;

    await axios.delete(`/api/${apiPrefix}/v1/push-subscriptions`, {
        data: { endpoint: sub.endpoint },
    });

    await sub.unsubscribe();
    isSubscribed.value = false;
}

export function usePushNotifications() {
    return { isSupported, permission, isSubscribed, checkSubscription, subscribe, unsubscribe };
}
