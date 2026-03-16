import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://localhost:8080';
const token = __ENV.TOKEN;
const peerId = __ENV.PEER_ID;
const sendRatio = Number(__ENV.SEND_RATIO || '0.3');
const listLimit = Number(__ENV.LIST_LIMIT || '100');

const sendDuration = new Trend('dialog_send_duration', true);
const listDuration = new Trend('dialog_list_duration', true);
const sendFailed = new Rate('dialog_send_failed');
const listFailed = new Rate('dialog_list_failed');

export const options = {
  vus: Number(__ENV.VUS || '30'),
  duration: __ENV.DURATION || '60s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500'],
    dialog_send_failed: ['rate<0.01'],
    dialog_list_failed: ['rate<0.01'],
    dialog_send_duration: ['p(95)<500'],
    dialog_list_duration: ['p(95)<500'],
  },
};

export function setup() {
  if (!token) {
    throw new Error('TOKEN is required');
  }

  if (!peerId) {
    throw new Error('PEER_ID is required');
  }
}

export default function () {
  const headers = {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };

  if (Math.random() < sendRatio) {
    const sendRes = http.post(
      `${baseUrl}/dialog/${peerId}/send`,
      JSON.stringify({
        text: `k6-message-vu-${__VU}-iter-${__ITER}-${Date.now()}`,
      }),
      { headers, tags: { operation: 'send' } }
    );

    const ok = check(sendRes, {
      'send status is 200': (r) => r.status === 200,
    });
    sendDuration.add(sendRes.timings.duration);
    sendFailed.add(!ok);
  } else {
    const listRes = http.get(`${baseUrl}/dialog/${peerId}/list?limit=${listLimit}`, {
      headers,
      tags: { operation: 'list' },
    });

    const ok = check(listRes, {
      'list status is 200': (r) => r.status === 200,
    });
    listDuration.add(listRes.timings.duration);
    listFailed.add(!ok);
  }

  sleep(0.1);
}
