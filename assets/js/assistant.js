document.addEventListener('DOMContentLoaded', () => {
  const widget = document.querySelector('#giftly-assistant');
  if (!widget) return;
  const panel = widget.querySelector('.assistant-panel');
  const launcher = widget.querySelector('.assistant-launcher');
  const close = widget.querySelector('.assistant-close');
  const clear = widget.querySelector('.assistant-clear');
  const list = widget.querySelector('.assistant-messages');
  const form = widget.querySelector('.assistant-form');
  const input = widget.querySelector('#assistant-input');
  const suggestions = widget.querySelector('.assistant-suggestions');
  const baseUrl = widget.dataset.baseUrl;
  let busy = false;
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  const stamp = value => {
    const date = value ? new Date(value) : new Date();
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  };
  const appendMessage = (role, text, time) => {
    const row = document.createElement('article');
    row.className = `assistant-message assistant-message-${role}`;
    const bubble = document.createElement('p');
    bubble.textContent = text;
    const footer = document.createElement('time');
    footer.textContent = stamp(time);
    row.append(bubble, footer);
    list.append(row);
    list.scrollTop = list.scrollHeight;
    return row;
  };
  const appendReply = (reply, time) => {
    const row = appendMessage('assistant', reply.text, time);
    (reply.cards || []).forEach(card => {
      const link = document.createElement('a');
      link.className = 'assistant-request-card';
      link.href = `${baseUrl}donor/request.php?id=${encodeURIComponent(card.request_id)}`;
      const title = document.createElement('strong');
      title.textContent = card.title;
      const charity = document.createElement('span');
      charity.textContent = `${card.organization_name} · ${card.category} · ${card.size_label}${card.wardrobe_item_name ? ` · Your ${card.wardrobe_item_name}` : ''}`;
      const meta = document.createElement('small');
      meta.textContent = `${card.remaining} needed · ${card.urgency} · ${card.suggested_quantity} suggested`;
      const action = document.createElement('b');
      action.textContent = 'View request →';
      link.append(title, charity, meta, action);
      row.append(link);
    });
    (reply.links || []).forEach(item => {
      const link = document.createElement('a');
      link.className = 'assistant-quick-link';
      link.href = `${baseUrl}${item.path}`;
      link.textContent = item.label;
      row.append(link);
    });
    list.scrollTop = list.scrollHeight;
  };
  const request = async (action, message) => {
    const response = await fetch(widget.dataset.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': widget.dataset.csrf },
      body: JSON.stringify({ action, message })
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'The assistant could not respond.');
    return data;
  };
  const showWelcome = () => {
    if (list.childElementCount) return;
    appendMessage('assistant', 'Hi! I’m the GIFTLY Assistant. Ask me about clothing needs, pledges, drop-offs, or how Giftly works — I can keep the conversation going.', new Date().toISOString());
  };
  const loadHistory = async () => {
    try {
      const data = await request('history');
      if (data.messages.length) {
        list.replaceChildren();
        data.messages.forEach(message => appendMessage(message.role, message.message, message.created_at));
      } else {
        showWelcome();
      }
    } catch (error) {
      showWelcome();
      appendMessage('assistant', error.message, new Date().toISOString());
    }
  };
  const openPanel = () => {
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
    launcher.classList.add('is-hidden');
    loadHistory();
    input.focus();
  };
  const closePanel = () => {
    panel.hidden = true;
    launcher.setAttribute('aria-expanded', 'false');
    launcher.classList.remove('is-hidden');
    launcher.focus();
  };
  launcher.addEventListener('click', openPanel);
  close.addEventListener('click', closePanel);
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) closePanel(); });
  suggestions.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (button && !busy) {
      input.value = button.textContent;
      form.requestSubmit();
    }
  });
  clear.addEventListener('click', async () => {
    if (busy) return;
    try {
      await request('clear');
      list.replaceChildren();
      showWelcome();
    } catch (error) {
      appendMessage('assistant', error.message, new Date().toISOString());
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const message = input.value.trim();
    if (!message || busy) return;
    busy = true;
    input.value = '';
    suggestions.hidden = true;
    appendMessage('user', message, new Date().toISOString());
    const typing = document.createElement('div');
    typing.className = 'assistant-typing';
    typing.setAttribute('aria-label', 'Assistant is typing');
    typing.innerHTML = '<i></i><i></i><i></i>';
    list.append(typing);
    list.scrollTop = list.scrollHeight;
    try {
      const data = await request('message', message);
      typing.remove();
      appendReply(data.reply, new Date().toISOString());
    } catch (error) {
      typing.remove();
      appendMessage('assistant', error.message, new Date().toISOString());
    } finally {
      busy = false;
      input.focus();
    }
  });
  if (!panel.hidden) loadHistory();
});
