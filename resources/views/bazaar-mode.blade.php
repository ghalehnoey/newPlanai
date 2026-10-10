@if($bazaarMode ?? false)
<p data-bazaar-status role="status" aria-live="polite"></p>
<script>
(() => {
  if (window.__BAZAAR_CHECKOUT_BOUND__) return;
  window.__BAZAAR_CHECKOUT_BOUND__ = true;
  const checkoutEnabled = @json((bool) config('services.bazaar.enabled', false));

  const report = (message) => {
    const status = document.querySelector('[data-bazaar-status]');
    if (status) status.textContent = message;
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
    if (!checkoutEnabled) {
      report('پرداخت بازار هنوز فعال نشده است.');
      return;
    }
    const bridge = getBridge();
    if (!bridge) {
      report('این صفحه را داخل اپلیکیشن بازار باز کنید.');
      return;
    }
    report('در حال اتصال به پرداخت بازار…');
    const productId = button.dataset.bazaarProduct;
    const payload = button.dataset.bazaarPayload || '';
    const dynamicPriceToken = button.dataset.bazaarDynamicPriceToken || null;
    if (button.dataset.bazaarSubscription === '1') bridge.subscribe(productId, payload, dynamicPriceToken);
    else bridge.purchase(productId, payload, dynamicPriceToken);
  }, true);

  getBridge();
  window.addEventListener('bazaar:ready', getBridge);
  window.addEventListener('bazaar:error', () => report('پرداخت بازار انجام نشد.'));
  window.addEventListener('bazaar:status', (event) => {
    if (event.detail === 'purchase_canceled') report('پرداخت لغو شد.');
  });
  window.addEventListener('bazaar:purchase', (event) => {
    const detail = event.detail || {};
    if (detail.status === 'verification_failed') {
      report('تأیید خرید کامل نشد.');
      return;
    }
    if (detail.status === 'success') {
      report('خرید تأیید شد.');
      window.location.reload();
    }
  });
})();
</script>
@endif
