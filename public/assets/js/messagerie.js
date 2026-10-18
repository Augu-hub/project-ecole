(function () {
  'use strict';

  var config = window.MESSAGERIE_CONFIG;

  var currentConversationId = null;

  var $list = document.getElementById('conversations-list');
  var $thread = document.getElementById('messages-thread');
  var $titre = document.getElementById('conversation-titre');
  var $input = document.getElementById('message-input');
  var $submit = document.getElementById('message-submit');
  var $form = document.getElementById('message-form');
  var $searchInput = document.getElementById('parent-search-input');
  var $searchResults = document.getElementById('parent-search-results');

  var subscribedChannels = {};

  function jsonHeaders(extra) {
    var headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (extra) Object.assign(headers, extra);
    return headers;
  }

  function postHeaders() {
    return jsonHeaders({ 'X-CSRF-TOKEN': config.csrfToken });
  }

  // Rejette avec un message exploitable si la réponse n'est pas OK.
  function fetchJson(url, options) {
    return fetch(url, options).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (body) {
        if (!r.ok) {
          var message = body && body.error ? body.error : ('Erreur ' + r.status);
          return Promise.reject(new Error(message));
        }
        return body;
      });
    });
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function formatTime(raw) {
    if (!raw) return '';
    var d = new Date(raw);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  function urlPour(template, id) {
    return template.replace('__ID__', id);
  }

  // ─── Conversations ──────────────────────────────────────────────────
  function loadConversations() {
    fetchJson(config.urls.conversations, { headers: jsonHeaders() })
      .then(function (rows) {
        renderConversations(rows);
        // Rejoint le canal de chaque conversation pour que la liste se mette à jour en temps
        // réel, même quand aucune conversation n'est ouverte.
        rows.forEach(function (c) { subscribeToConversation(c.id); });
      })
      .catch(function (err) {
        $list.innerHTML = '<li class="list-group-item text-danger">Erreur de chargement (' + escapeHtml(err.message) + ')</li>';
      });
  }

  function renderConversations(rows) {
    if (!rows.length) {
      $list.innerHTML = '<li class="list-group-item text-center text-secondary-light">Aucune conversation</li>';
      return;
    }

    $list.innerHTML = rows.map(function (c) {
      var nom = escapeHtml((c.parent_prenom || '') + ' ' + (c.parent_nom || ''));
      var badge = c.unread_count > 0
        ? '<span class="badge bg-danger rounded-pill">' + (c.unread_count > 9 ? '9+' : c.unread_count) + '</span>'
        : '';
      var selected = c.id === currentConversationId ? 'conv-selected' : '';

      return '<li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ' + selected + '" ' +
        'style="cursor:pointer" data-id="' + c.id + '" data-parent-id="' + c.parent_id + '" data-nom="' + nom + '">' +
        '<div><div class="fw-semibold">' + nom + '</div>' +
        '<div class="text-secondary-light" style="font-size:12px;">' + escapeHtml(c.last_message || '') + '</div></div>' +
        badge +
        '</li>';
    }).join('');

    Array.prototype.forEach.call($list.querySelectorAll('li[data-id]'), function (li) {
      li.addEventListener('click', function () {
        openConversation(Number(li.dataset.id), li.dataset.nom);
      });
    });
  }

  function openConversation(conversationId, label) {
    currentConversationId = conversationId;
    $titre.textContent = label;
    $thread.innerHTML = '<p class="text-center text-secondary-light">Chargement...</p>';
    $input.disabled = false;
    $submit.disabled = false;

    subscribeToConversation(conversationId);

    fetchJson(urlPour(config.urls.messages, conversationId), { headers: jsonHeaders() })
      .then(function (messages) {
        renderMessages(messages);
        marquerLu(conversationId);
      })
      .catch(function (err) {
        $thread.innerHTML = '<p class="text-center text-danger">Erreur de chargement (' + escapeHtml(err.message) + ')</p>';
      });

    // Rafraîchit la liste pour refléter la sélection visuelle et le compteur non-lu.
    loadConversations();
  }

  function renderMessages(messages) {
    $thread.innerHTML = messages.map(renderBubble).join('');
    $thread.scrollTop = $thread.scrollHeight;
  }

  function renderBubble(msg) {
    var mine = msg.sender_type === 'admin';
    var align = mine ? 'justify-content-end' : 'justify-content-start';
    var bg = mine ? 'bg-primary text-white' : 'bg-light text-dark';

    return '<div class="d-flex ' + align + ' mb-2">' +
      '<div class="p-2 rounded-3 ' + bg + '" style="max-width:70%;">' +
      '<div>' + escapeHtml(msg.body) + '</div>' +
      '<div style="font-size:10px; opacity:.7;">' + formatTime(msg.created_at) + '</div>' +
      '</div></div>';
  }

  function appendMessage(msg) {
    var atBottom = $thread.scrollTop + $thread.clientHeight >= $thread.scrollHeight - 20;
    $thread.insertAdjacentHTML('beforeend', renderBubble(msg));
    if (atBottom) $thread.scrollTop = $thread.scrollHeight;
  }

  function marquerLu(conversationId) {
    fetch(urlPour(config.urls.marquerLu, conversationId), { method: 'POST', headers: postHeaders() });
  }

  // ─── Recherche parent / démarrage conversation ─────────────────────
  var searchTimer = null;
  $searchInput.addEventListener('input', function () {
    var q = $searchInput.value.trim();
    clearTimeout(searchTimer);
    if (q.length < 2) {
      $searchResults.classList.add('d-none');
      return;
    }
    searchTimer = setTimeout(function () { searchParent(q); }, 300);
  });

  function searchParent(q) {
    fetchJson(config.urls.rechercherParent + '?q=' + encodeURIComponent(q), { headers: jsonHeaders() })
      .then(function (results) {
        if (!results.length) {
          $searchResults.innerHTML = '<div class="list-group-item text-secondary-light">Aucun résultat</div>';
        } else {
          $searchResults.innerHTML = results.map(function (p) {
            var enfants = (p.enfants || []).join(', ');
            return '<button type="button" class="list-group-item list-group-item-action" data-id="' + p.id + '" data-nom="' + escapeHtml(p.nom_complet) + '">' +
              '<div class="fw-semibold">' + escapeHtml(p.nom_complet) + '</div>' +
              '<div class="text-secondary-light" style="font-size:12px;">' + escapeHtml(enfants) + '</div>' +
              '</button>';
          }).join('');
        }
        $searchResults.classList.remove('d-none');

        Array.prototype.forEach.call($searchResults.querySelectorAll('button[data-id]'), function (btn) {
          btn.addEventListener('click', function () {
            startConversationWith(Number(btn.dataset.id), btn.dataset.nom);
          });
        });
      })
      .catch(function (err) {
        $searchResults.classList.remove('d-none');
        $searchResults.innerHTML = '<div class="list-group-item text-danger">Erreur de recherche (' + escapeHtml(err.message) + ')</div>';
      });
  }

  function startConversationWith(parentId, label) {
    fetchJson(config.urls.creerConversation, {
      method: 'POST',
      headers: postHeaders(),
      body: JSON.stringify({ counterpart_id: parentId }),
    })
      .then(function (conv) {
        $searchResults.classList.add('d-none');
        $searchInput.value = '';
        openConversation(conv.id, label);
      })
      .catch(function (err) {
        window.alert('Impossible de démarrer la conversation : ' + err.message);
      });
  }

  // ─── Envoi de message ───────────────────────────────────────────────
  $form.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = $input.value.trim();
    if (!body || !currentConversationId) return;

    $input.value = '';
    fetchJson(urlPour(config.urls.envoyerMessage, currentConversationId), {
      method: 'POST',
      headers: postHeaders(),
      body: JSON.stringify({ body: body }),
    }).catch(function (err) {
      window.alert('Erreur lors de l\'envoi : ' + err.message);
    });
    // Le rendu se fait via l'événement broadcast "new_message" reçu sur le canal
    // (voir subscribeToConversation), pas ici, pour ne jamais désynchroniser d'un id serveur.
  });

  // ─── Laravel Echo / Reverb ──────────────────────────────────────────
  var echo = new Echo.default({
    broadcaster: 'reverb',
    key: config.reverb.key,
    wsHost: config.reverb.host,
    wsPort: config.reverb.port,
    wssPort: config.reverb.port,
    forceTLS: config.reverb.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
  });

  function subscribeToConversation(conversationId) {
    if (subscribedChannels[conversationId]) return;
    subscribedChannels[conversationId] = true;

    echo.private('conversation.' + conversationId).listen('.new_message', function (msg) {
      if (msg.conversation_id === currentConversationId) {
        appendMessage(msg);
        marquerLu(currentConversationId);
      }
      loadConversations();
    });
  }

  loadConversations();
})();
