import axios from 'axios';

(window as Window & typeof globalThis & { axios: typeof axios }).axios = axios;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
