document.addEventListener('DOMContentLoaded', () => {
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
