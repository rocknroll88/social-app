import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://localhost:8085';
const userId = __ENV.USER_ID;

const userGetDuration = new Trend('user_get_duration', true);
const userGetFailed = new Rate('user_get_failed');

export const options = {
  vus: Number(__ENV.VUS || '40'),
  duration: __ENV.DURATION || '120s',
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<500'],
    user_get_failed: ['rate<0.05'],
    user_get_duration: ['p(95)<500'],
  },
};

export function setup() {
  if (!userId) {
    throw new Error('USER_ID is required');
  }
}

export default function () {
  const response = http.get(`${baseUrl}/user/get/${userId}`, {
    headers: {
      'X-Request-Id': `ha-user-get-${__VU}-${__ITER}`,
    },
    tags: { operation: 'user_get' },
  });

  const ok = check(response, {
    'user/get status is 200': (r) => r.status === 200,
  });

  userGetDuration.add(response.timings.duration);
  userGetFailed.add(!ok);
  sleep(0.1);
}
