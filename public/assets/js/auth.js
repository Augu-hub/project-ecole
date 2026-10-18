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
      bouton.disabled = true;
      bouton.textContent = formulaire.getAttribute('data-attente');
    });
  });
})();
