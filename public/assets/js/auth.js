/*
 * Pages d'authentification (mise en page resources/views/layouts/auth.blade.php).
 * - Bouton « Afficher / Masquer » : <button class="mdp-bascule" aria-controls="id-du-champ">
 * - Bouton d'envoi désactivé pendant l'envoi : <form data-attente="Texte affiché pendant l'envoi…">
 */
(function () {
  document.querySelectorAll('.mdp-bascule[aria-controls]').forEach(function (bascule) {
    var champ = document.getElementById(bascule.getAttribute('aria-controls'));
    if (!champ) return;

    bascule.addEventListener('click', function () {
      var visible = champ.type === 'text';
      champ.type = visible ? 'password' : 'text';
      bascule.textContent = visible ? 'Afficher' : 'Masquer';
      bascule.setAttribute('aria-pressed', String(!visible));
      champ.focus();
    });
  });

  document.querySelectorAll('form[data-attente]').forEach(function (formulaire) {
    formulaire.addEventListener('submit', function () {
      var bouton = formulaire.querySelector('button[type="submit"]');
      if (!bouton) return;
      if (!bouton.hasAttribute('data-texte')) bouton.setAttribute('data-texte', bouton.textContent);
      bouton.disabled = true;
      bouton.textContent = formulaire.getAttribute('data-attente');
    });
  });

  // Retour arrière (cache du navigateur) : la page revient telle quelle, bouton encore bloqué → on le réactive.
  window.addEventListener('pageshow', function (evenement) {
    if (!evenement.persisted) return;
    document.querySelectorAll('form[data-attente] button[type="submit"][data-texte]').forEach(function (bouton) {
      bouton.disabled = false;
      bouton.textContent = bouton.getAttribute('data-texte');
    });
  });
})();
