document.addEventListener('DOMContentLoaded', function () {
  // Auto-hide flash messages
  var flashes = document.querySelectorAll('.flash');
  flashes.forEach(function (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity 0.5s';
      flash.style.opacity = '0';
      setTimeout(function () { flash.remove(); }, 500);
    }, 5000);
  });

  // Gallery thumbnail clicks
  document.querySelectorAll('.gallery-thumbs .thumb').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      var main = this.closest('.product-gallery').querySelector('.gallery-main img');
      if (main) main.src = this.src;
    });
  });

  // Cart quantity auto-submit
  document.querySelectorAll('.cart-item-qty input[type="number"]').forEach(function (input) {
    input.addEventListener('change', function () {
      this.closest('form').submit();
    });
  });

  // Quick add button feedback
  document.querySelectorAll('.quick-add .button').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var original = this.textContent;
      this.textContent = 'Adding...';
      this.disabled = true;
      var form = this.closest('form');
      if (form) form.submit();
    });
  });

  // FAQ accordion smooth scroll
  document.querySelectorAll('.faq-nav a').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.open = true;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // Payment method highlight
  document.querySelectorAll('.payment-option').forEach(function (opt) {
    opt.addEventListener('click', function () {
      var radio = this.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    });
  });
});
