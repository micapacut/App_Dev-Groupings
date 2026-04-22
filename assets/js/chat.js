/**
 * Chatbot UI: calls API (database-first, Gemini fallback)
 */

(function () {
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  const sendBtn = document.getElementById('chat-send');
  const messagesEl = document.getElementById('chat-messages');

  // Resolve API URL relative to current page (works from /newbot/ or /newbot/index.php)
  const base = window.location.pathname.replace(/\/[^/]*$/, '') || '';
  const apiUrl = (base ? base + '/' : '') + 'api/chat.php';

  function addMessage(text, isUser, source) {
    const div = document.createElement('div');
    div.className = 'message ' + (isUser ? 'user' : 'bot');
    if (!isUser && source) {
      const tag = document.createElement('span');
      tag.className = 'source-tag ' + (source === 'database' ? 'database' : source === 'gemini' ? 'gemini' : 'error');
      tag.textContent = source === 'database' ? 'Database' : source === 'gemini' ? 'AI (Gemini)' : 'Notice';
      div.appendChild(tag);
    }
    const p = document.createElement('p');
    p.textContent = text;
    div.appendChild(p);
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function setLoading(loading) {
    sendBtn.disabled = loading;
    sendBtn.textContent = loading ? '…' : 'Send';
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const message = input.value.trim();
    if (!message) return;

    addMessage(message, true);
    input.value = '';
    setLoading(true);

    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: message }),
      });
      const text = await res.text();
      let data = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch (_) {
        addMessage('Server returned invalid response. Check PHP errors.', false, 'error');
        setLoading(false);
        return;
      }

      if (data.answer != null && data.answer !== '') {
        addMessage(data.answer, false, data.source || 'database');
      } else {
        addMessage(data.error || 'Something went wrong. Please try again.', false, 'error');
      }
    } catch (err) {
      addMessage('Network error. Is the server running? ' + (err.message || ''), false, 'error');
    } finally {
      setLoading(false);
    }
  });

  // Tab switching
  document.querySelectorAll('.tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      const tabName = this.getAttribute('data-tab');
      document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
      document.querySelectorAll('.panel').forEach(function (p) { p.classList.remove('active'); });
      this.classList.add('active');
      const panel = document.getElementById('panel-' + tabName);
      if (panel) panel.classList.add('active');
    });
  });
})();
