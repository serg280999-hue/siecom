// Nutzung: data-checkout-form="1" auf Formular setzen,
// window.CHECKOUT_API_BASE definieren und ../_shared/checkout.js einbinden.
(function () {
  const STATUS_START = 'Weiterleitung zur Zahlung...';
  const STATUS_ERROR = 'Zahlung konnte nicht gestartet werden. Bitte erneut versuchen.';

  // Safe Meta Pixel call (не ломает сайт, если fbq заблокирован)
  const fbqSafe = (...args) => {
    try {
      if (typeof window.fbq === 'function') {
        window.fbq(...args);
      }
    } catch (e) {}
  };

  const detectLanding = () => {
    const match = window.location.pathname.match(/\/landings\/([^/]+)/);
    if (match && match[1]) return match[1];

    const segments = window.location.pathname.split('/').filter(Boolean);
    const last = segments[segments.length - 1] || '';
    return last.replace(/index\.html?$/i, '') || last;
  };

  const getField = (form, field) =>
    form.querySelector(`[name="${field}"]`) ||
    form.querySelector(`[data-field="${field}"]`);

  const getStatusElement = (form, submitButton) => {
    const existing = form.querySelector('[data-checkout-status]');
    if (existing) return existing;

    const statusEl = document.createElement('p');
    statusEl.setAttribute('data-checkout-status', '1');
    statusEl.className = 'text-sm text-slate-700';

    if (submitButton && submitButton.parentElement) {
      submitButton.insertAdjacentElement('afterend', statusEl);
    } else {
      form.appendChild(statusEl);
    }

    return statusEl;
  };

  const setStatus = (statusEl, message, isError) => {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('text-red-600', Boolean(isError));
    statusEl.classList.toggle('text-emerald-700', !isError);
  };

  const getUtmParams = () =>
    Object.fromEntries(new URLSearchParams(window.location.search));

  const forms = document.querySelectorAll('[data-checkout-form="1"]');
  if (!forms.length) return;

  forms.forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const submitButton = form.querySelector(
        'button[type="submit"], input[type="submit"]'
      );
      const statusEl = getStatusElement(form, submitButton);

      const nameInput = getField(form, 'name');
      const phoneInput = getField(form, 'phone');
      const addressInput = getField(form, 'address');
      const quantityInput = getField(form, 'quantity');
      const consentInput = getField(form, 'consent');

      const phone = phoneInput ? phoneInput.value.trim() : '';
      const phoneDigits = phone.replace(/\D/g, '');
      if (phoneDigits.length < 7) {
        setStatus(statusEl, STATUS_ERROR, true);
        return;
      }

      if (consentInput && !consentInput.checked) {
        setStatus(statusEl, STATUS_ERROR, true);
        return;
      }

      const apiBase = window.CHECKOUT_API_BASE;
      if (!apiBase) {
        setStatus(statusEl, STATUS_ERROR, true);
        return;
      }

      if (submitButton) submitButton.disabled = true;
      setStatus(statusEl, STATUS_START, false);

      const quantityValue =
        quantityInput && quantityInput.value
          ? parseInt(quantityInput.value, 10)
          : 1;

      const payload = {
        name: nameInput ? nameInput.value.trim() : '',
        phone,
        address: addressInput ? addressInput.value.trim() : '',
        quantity:
          Number.isFinite(quantityValue) && quantityValue > 0
            ? quantityValue
            : 1,
        landing: detectLanding(),
        page_url: window.location.href,
        utm: getUtmParams(),
      };

      try {
        const response = await fetch(`${apiBase}/api/create_checkout.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        if (!response.ok) throw new Error('Request failed');

        const data = await response.json();
        if (data && data.ok && data.redirect_url) {
          // 🔥 Meta Pixel — InitiateCheckout (only when checkout is реально создан)
          fbqSafe('track', 'InitiateCheckout', {
            content_name: document.title || 'Product',
            content_type: 'product',
            currency: 'EUR',
          });

          window.location.href = data.redirect_url;
          return;
        }

        throw new Error('Invalid response');
      } catch (error) {
        console.error('Checkout error:', error);
        setStatus(statusEl, STATUS_ERROR, true);
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });
  });
})();
