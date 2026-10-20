@if($bazaarMode ?? false)
<p data-bazaar-status role="status" aria-live="polite" hidden
   class="mb-6 rounded-xl border border-purple-400/60 bg-purple-950/80 px-5 py-4 text-white"></p>
<script>
(() => {
  if (window.__BAZAAR_CHECKOUT_BOUND__) return;
  window.__BAZAAR_CHECKOUT_BOUND__ = true;
  const checkoutEnabled = @json((bool) config('services.bazaar.enabled', false));
  let busyButtons = [];
  let startTimeout = null;

  const report = (message) => {
    const status = document.querySelector('[data-bazaar-status]');
    if (status) {
      status.hidden = false;
      status.textContent = message;
      status.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  };
  const clearStartTimeout = () => {
    if (startTimeout !== null) window.clearTimeout(startTimeout);
    startTimeout = null;
  };
  const finish = () => {
    clearStartTimeout();
    busyButtons.forEach(({ button, disabled }) => {
      button.disabled = disabled;
      button.removeAttribute('aria-busy');
    });
    busyButtons = [];
  };

  const getBridge = () => {
    if (window.BazaarApp) return window.BazaarApp;
    const native = window.AndroidBazaar;
    if (!native || typeof native.purchase !== 'function' || typeof native.subscribe !== 'function') return null;
    window.BazaarApp = {
      purchase: (productId, payload, token) => native.purchase(String(productId), payload || null, token || null),
      subscribe: (productId, payload, token) => native.subscribe(String(productId), payload || null, token || null),
      sync: () => native.sync()
    };
    return window.BazaarApp;
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest && event.target.closest('[data-bazaar-product]');
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    if (busyButtons.length || button.disabled) return;
    if (!checkoutEnabled) {
      report('خرید اشتراک از بازار در حال آماده‌سازی است. لطفاً بعداً دوباره تلاش کنید.');
      return;
    }
    const bridge = getBridge();
    if (!bridge) {
      report('برای خرید از بازار، این صفحه را داخل اپلیکیشن وکیل هوشمند باز کنید.');
      return;
    }
    busyButtons = Array.from(document.querySelectorAll('[data-bazaar-product]'), (item) => {
      const disabled = item.disabled;
      item.disabled = true;
      item.setAttribute('aria-busy', 'true');
      return { button: item, disabled };
    });
    report('در حال باز کردن پنجره پرداخت بازار…');
    startTimeout = window.setTimeout(() => {
      finish();
      report('پنجره پرداخت باز نشد. نصب بودن و اتصال برنامه بازار را بررسی کنید و دوباره تلاش کنید.');
    }, 30000);
    try {
      const productId = button.dataset.bazaarProduct;
      const payload = button.dataset.bazaarPayload || '';
      const token = button.dataset.bazaarDynamicPriceToken || null;
      if (button.dataset.bazaarSubscription === '1') bridge.subscribe(productId, payload, token);
      else bridge.purchase(productId, payload, token);
    } catch (error) {
      finish();
      report('پنجره پرداخت بازار باز نشد. برنامه را دوباره باز کنید و تلاش کنید.');
    }
  }, true);

  getBridge();
  window.addEventListener('bazaar:ready', getBridge);
  window.addEventListener('bazaar:error', () => {
    finish();
    report('پرداخت انجام نشد. نصب بودن و اتصال برنامه بازار را بررسی کنید و دوباره تلاش کنید.');
  });
  window.addEventListener('bazaar:status', (event) => {
    if (event.detail === 'purchase_started') {
      clearStartTimeout();
      report('پنجره پرداخت بازار باز شد. پس از پرداخت، اشتراک شما بررسی می‌شود.');
    } else if (event.detail === 'purchase_canceled') {
      finish();
      report('پرداخت لغو شد؛ می‌توانید دوباره تلاش کنید.');
    }
  });
  window.addEventListener('bazaar:purchase', (event) => {
    const detail = event.detail || {};
    if (detail.status === 'verification_failed') {
      finish();
      report('تأیید خرید کامل نشد. اگر مبلغ کسر شده است، اپ را دوباره باز کنید تا خرید بازیابی شود.');
      return;
    }
    if (detail.status !== 'success') return;
    finish();
    report('خرید تأیید شد و اشتراک فعال است.');
    // Restoring the same receipt on page load must not cause a reload loop.
    const receiptId = JSON.stringify([detail.product_id, detail.order_id, detail.purchase_time]);
    try {
      if (sessionStorage.getItem('bazaar:last-refreshed-receipt') === receiptId) return;
      sessionStorage.setItem('bazaar:last-refreshed-receipt', receiptId);
    } catch (error) {
      return;
    }
    window.location.reload();
  });
})();
</script>
@endif