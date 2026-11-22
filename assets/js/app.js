document.addEventListener('DOMContentLoaded', () => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isHomepage = document.querySelector('.hero') && document.querySelector('#how-it-works');

  if (isHomepage && !reduceMotion) {
    document.querySelectorAll('.hero-grid > *, .stat-grid > *, .section-head, .step, .journey-card, .request-card, .home-faq, .impact-cta').forEach((element, index) => {
      element.classList.add('giftly-reveal');
      element.style.setProperty('--reveal-delay', `${Math.min(index % 6, 5) * 70}ms`);
    });

    const revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px' });

    document.querySelectorAll('.giftly-reveal').forEach(element => revealObserver.observe(element));

    document.querySelectorAll('.step, .journey-card, .request-card').forEach(card => {
      card.addEventListener('pointermove', event => {
        if (window.matchMedia('(pointer: coarse)').matches) return;
        const rect = card.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        card.style.setProperty('--card-x', `${x * 7}px`);
        card.style.setProperty('--card-y', `${y * -7}px`);
      });
      card.addEventListener('pointerleave', () => {
        card.style.setProperty('--card-x', '0px');
        card.style.setProperty('--card-y', '0px');
      });
    });
  }

  if (isHomepage) {
    document.querySelectorAll('[data-count]').forEach(node => {
      const end = Number(node.dataset.count || 0);
      if (!Number.isFinite(end)) return;
      if (reduceMotion) {
        node.textContent = end.toLocaleString();
        return;
      }
      const duration = 1200;
      const start = performance.now();
      const count = now => {
        const progress = Math.min(1, (now - start) / duration);
        node.textContent = Math.round(end * (1 - Math.pow(1 - progress, 3))).toLocaleString();
        if (progress < 1) requestAnimationFrame(count);
      };
      requestAnimationFrame(count);
    });
  }

  const toggle = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  toggle?.addEventListener('click', () => { const open = menu.classList.toggle('open'); toggle.setAttribute('aria-expanded', open); });
  document.querySelectorAll('[data-dismiss]').forEach(button => button.addEventListener('click', () => button.closest('.flash').remove()));
  document.querySelectorAll('form[data-loading]').forEach(form => form.addEventListener('submit', event => {
    if (!form.checkValidity()) return;
    if (!form.dataset.confirm || confirm(form.dataset.confirm)) {
      if (form.dataset.confirm) event.preventDefault();
      if (form.dataset.confirm && !form.dataset.confirmed) { form.dataset.confirmed = 'true'; form.submit(); return; }
      document.body.insertAdjacentHTML('beforeend', '<div class="loading-overlay"><span>Saving your impact…</span></div>');
    } else { event.preventDefault(); }
  }));
  const list = document.querySelector('[data-requirements]');
  const add = document.querySelector('[data-add-requirement]');
  add?.addEventListener('click', () => {
    const item = list.querySelector('.requirement-form-item').cloneNode(true);
    item.querySelectorAll('input, select').forEach(el => { if (el.name === 'quantity[]') el.value = '1'; else if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = ''; });
    list.appendChild(item);
  });
  list?.addEventListener('click', event => { if (event.target.closest('.remove-req') && list.children.length > 1) event.target.closest('.requirement-form-item').remove(); });
});
