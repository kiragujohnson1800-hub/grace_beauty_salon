(function () {
  const apiRoot = '../backend/api/';

  async function request(endpoint, options) {
    const settings = options || {};
    const headers = new Headers(settings.headers || {});
    headers.set('Accept', 'application/json');
    const csrf = sessionStorage.getItem('grace_csrf_token');
    if (csrf) headers.set('X-CSRF-Token', csrf);
    if (settings.body && !(settings.body instanceof FormData)) {
      headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(apiRoot + endpoint.replace(/^\/+/, ''), {
      ...settings,
      headers,
      credentials: 'same-origin'
    });
    const contentType = response.headers.get('content-type') || '';
    let payload;
    if (contentType.includes('application/json')) {
      payload = await response.json();
    } else {
      const text = await response.text();
      throw new Error(text ? 'The server returned an unexpected response.' : 'The server returned an empty response.');
    }

    const data = payload && payload.data;
    if (data && data.csrf_token) sessionStorage.setItem('grace_csrf_token', data.csrf_token);
    if (!response.ok || payload.success === false) {
      const error = new Error(payload.message || 'Something went wrong. Please try again.');
      error.status = response.status;
      error.payload = payload;
      throw error;
    }
    return payload;
  }

  function get(endpoint) {
    return request(endpoint, { method: 'GET' });
  }

  function post(endpoint, data) {
    return request(endpoint, { method: 'POST', body: JSON.stringify(data || {}) });
  }

  function patch(endpoint, data) {
    return request(endpoint, { method: 'PATCH', body: JSON.stringify(data || {}) });
  }

  function remove(endpoint) {
    return request(endpoint, { method: 'DELETE' });
  }

  function unwrap(payload) {
    return payload && payload.data !== undefined ? payload.data : null;
  }

  function list(payload, key) {
    const data = unwrap(payload);
    return data && Array.isArray(data[key]) ? data[key] : [];
  }

  window.GraceApi = { get, post, patch, remove, unwrap, list };
})();