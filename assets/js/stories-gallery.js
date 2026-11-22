document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('#happy-gallery');
  const lightbox = document.querySelector('[data-gallery-lightbox]');

  if (grid && lightbox) {
    const image = lightbox.querySelector('[data-lightbox-image]');
    const caption = lightbox.querySelector('[data-lightbox-caption]');
    const charity = lightbox.querySelector('[data-lightbox-charity]');
    const location = lightbox.querySelector('[data-lightbox-location]');
    const category = lightbox.querySelector('[data-lightbox-category]');
    const campaignWrap = lightbox.querySelector('[data-lightbox-campaign-wrap]');
    const campaign = lightbox.querySelector('[data-lightbox-campaign]');
    const date = lightbox.querySelector('[data-lightbox-date]');
    const illustrativeNote = lightbox.querySelector('[data-lightbox-illustrative]');
    const removal = lightbox.querySelector('[data-lightbox-removal]');
    let activeIndex = -1;
    let returnFocus = null;

    const cards = () => Array.from(grid.querySelectorAll('[data-gallery-item]'));
    const show = index => {
      const items = cards();
      if (!items.length) return;
      activeIndex = (index + items.length) % items.length;
      const item = items[activeIndex];
      image.src = item.dataset.image;
      image.alt = item.dataset.caption;
      caption.textContent = item.dataset.caption;
      charity.textContent = item.dataset.charity;
      location.textContent = item.dataset.location ? ` · ${item.dataset.location}` : '';
      category.textContent = item.dataset.category;
      date.textContent = item.dataset.date;
      illustrativeNote.hidden = item.dataset.illustrative !== '1';
      campaignWrap.hidden = !item.dataset.campaign;
      campaign.textContent = item.dataset.campaign || '';
      const baseUrl = grid.dataset.baseUrl || '/';
      campaign.href = item.dataset.campaignId ? `${baseUrl}donor/request.php?id=${item.dataset.campaignId}` : '#';
      removal.href = `${baseUrl}public/removal_request.php?id=${item.dataset.id}`;
      lightbox.hidden = false;
      document.body.classList.add('lightbox-open');
      lightbox.querySelector('[data-lightbox-close]').focus();
    };
    const close = () => {
      if (lightbox.hidden) return;
      lightbox.hidden = true;
      image.src = '';
      document.body.classList.remove('lightbox-open');
      returnFocus?.focus();
    };

    grid.addEventListener('click', event => {
      const item = event.target.closest('[data-gallery-item]');
      if (item) {
        returnFocus = item;
        show(cards().indexOf(item));
      }
    });
    lightbox.querySelector('[data-lightbox-close]').addEventListener('click', close);
    lightbox.querySelector('[data-lightbox-prev]').addEventListener('click', () => show(activeIndex - 1));
    lightbox.querySelector('[data-lightbox-next]').addEventListener('click', () => show(activeIndex + 1));
    lightbox.addEventListener('click', event => { if (event.target === lightbox) close(); });
    document.addEventListener('keydown', event => {
      if (lightbox.hidden) return;
      if (event.key === 'Escape') close();
      if (event.key === 'ArrowLeft') show(activeIndex - 1);
      if (event.key === 'ArrowRight') show(activeIndex + 1);
      if (event.key === 'Tab') {
        const focusable = Array.from(lightbox.querySelectorAll('button:not([disabled]), a[href]:not([hidden])')).filter(element => element.getClientRects().length > 0);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });

    const loadMore = document.querySelector('#gallery-load-more');
    loadMore?.addEventListener('click', async () => {
      loadMore.disabled = true;
      loadMore.textContent = 'Loading moments…';
      const nextPage = Number(grid.dataset.page || 1) + 1;
      const query = new URLSearchParams({ format: 'json', page: String(nextPage) });
      if (grid.dataset.query) query.set('q', grid.dataset.query);
      if (grid.dataset.category && grid.dataset.category !== '0') query.set('category', grid.dataset.category);
      try {
        const response = await fetch(`${window.location.pathname}?${query.toString()}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('The gallery could not be loaded. Please try again.');
        const data = await response.json();
        data.items.forEach(item => {
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'gallery-card';
          button.dataset.galleryItem = '';
          button.dataset.id = String(item.id);
          button.dataset.image = item.image;
          button.dataset.caption = item.caption;
          button.dataset.charity = item.charity;
          button.dataset.campaign = item.campaign;
          button.dataset.campaignId = String(item.campaign_id);
          button.dataset.category = item.category;
          button.dataset.location = item.location;
          button.dataset.date = item.date;
          button.dataset.illustrative = item.illustrative ? '1' : '0';
          button.setAttribute('aria-label', `View photograph: ${item.caption}`);
          const img = document.createElement('img');
          img.src = item.image;
          img.alt = item.caption;
          img.loading = 'lazy';
          const overlay = document.createElement('span');
          overlay.className = 'gallery-card-overlay';
          const chip = document.createElement('span');
          chip.className = 'chip chip-sun';
          chip.textContent = item.category;
          if (item.illustrative) {
            const illustrativeChip = document.createElement('span');
            illustrativeChip.className = 'chip chip-sun';
            illustrativeChip.textContent = 'Illustrative';
            overlay.append(illustrativeChip);
          }
          const title = document.createElement('strong');
          title.textContent = item.caption;
          const source = document.createElement('small');
          source.textContent = `${item.charity}${item.location ? ` · ${item.location}` : ''}`;
          const zoom = document.createElement('span');
          zoom.className = 'gallery-card-zoom';
          zoom.setAttribute('aria-hidden', 'true');
          zoom.textContent = '↗';
          overlay.append(chip, title, source);
          button.append(img, overlay, zoom);
          grid.append(button);
        });
        grid.dataset.page = String(nextPage);
        loadMore.hidden = !data.has_more;
        loadMore.textContent = 'Load more moments ↓';
      } catch (error) {
        loadMore.textContent = error.message || 'Could not load photos';
        loadMore.disabled = false;
      }
    });
  }

  document.querySelectorAll('[data-share-story]').forEach(button => {
    button.addEventListener('click', async () => {
      const shareData = { title: button.dataset.title, url: window.location.href };
      try {
        if (navigator.share) await navigator.share(shareData);
        else if (navigator.clipboard) {
          await navigator.clipboard.writeText(shareData.url);
          button.textContent = 'Link copied ✓';
        } else {
          window.prompt('Copy this story link:', shareData.url);
        }
      } catch (error) {
        if (error.name !== 'AbortError') button.textContent = 'Could not share — copy the page link';
      }
    });
  });
});
